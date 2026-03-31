<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentWithdrawal;
use App\Models\AdminAccount;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentWithdrawalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_submit_a_withdrawal_request_without_deducting_wallet_balance(): void
    {
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 1200);

        $response = $this->actingAs($agentUser)
            ->post(route('agent.withdrawals.store'), [
                'amount' => 600,
                'payout_method' => 'bank',
                'account_holder_name' => 'Ravi Kumar',
                'account_number' => '1234567890',
                'ifsc_code' => 'HDFC0001234',
            ]);

        $response->assertRedirect(route('agent.withdrawals.index'));

        $this->assertDatabaseHas('agent_withdrawals', [
            'agent_id' => $agent->id,
            'amount' => 600,
            'payout_method' => 'bank',
            'status' => AgentWithdrawal::STATUS_PENDING,
        ]);

        $this->assertSame(1200.0, $agent->fresh()->wallet_balance);
    }

    public function test_admin_deducts_wallet_only_when_an_approved_withdrawal_is_completed(): void
    {
        $admin = $this->createAdmin();
        [, $agent] = $this->createApprovedAgent(walletBalance: 1000);

        $withdrawal = AgentWithdrawal::query()->create([
            'agent_id' => $agent->id,
            'amount' => 500,
            'payout_method' => 'upi',
            'upi_id' => 'pump@upi',
            'status' => AgentWithdrawal::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.approve', $withdrawal->id))
            ->assertRedirect();

        $this->assertSame(1000.0, $agent->fresh()->wallet_balance);
        $this->assertSame(AgentWithdrawal::STATUS_APPROVED, $withdrawal->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.withdrawals.complete', $withdrawal->id))
            ->assertRedirect();

        $this->assertSame(500.0, $agent->fresh()->wallet_balance);
        $this->assertSame(AgentWithdrawal::STATUS_COMPLETED, $withdrawal->fresh()->status);
        $this->assertNotNull($withdrawal->fresh()->processed_at);
    }

    public function test_otp_verified_delivery_settles_billing_and_credits_wallets(): void
    {
        $admin = $this->createAdmin();
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0, totalDeliveries: 0);
        $customer = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => 'on_the_way',
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Near City Center',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
            'delivery_otp' => '123456',
            'delivery_otp_generated_at' => now(),
        ]);

        app(BillingService::class)->finalizeBilling($order);

        $this->actingAs($agentUser)
            ->from(route('agent.active'))
            ->post(route('agent.status', $order->id), [
                'delivery_otp' => '123456',
            ])
            ->assertRedirect(route('agent.active'));

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(85.38, $agent->fresh()->wallet_balance);
        $this->assertSame(20.25, (float) AdminAccount::query()->firstOrFail()->wallet_balance);
        $this->assertDatabaseHas('billings', [
            'order_id' => $order->id,
            'billing_status' => 'paid',
            'settlement_status' => 'pending',
        ]);
        $this->assertSame($admin->id, AdminAccount::query()->firstOrFail()->user_id);
    }

    public function test_agent_active_page_shows_recent_delivered_order_after_otp_verification(): void
    {
        $this->createAdmin();
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0, totalDeliveries: 0);
        $customer = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $cancelledOrder = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_CANCELLED,
            'is_cancelled' => true,
            'cancelled_by' => FuelRequest::CANCELLED_BY_CUSTOMER,
            'cancellation_reason' => 'Cancelled by customer.',
            'cancelled_at' => now()->subMinutes(15),
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 603.55,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Old cancelled order',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
        ]);
        $cancelledOrder->forceFill([
            'updated_at' => now()->subMinutes(15),
        ])->saveQuietly();

        $deliveredOrder = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_OTP_VERIFICATION,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Fresh delivery order',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
            'delivery_otp' => '123456',
            'delivery_otp_generated_at' => now(),
        ]);

        app(BillingService::class)->finalizeBilling($deliveredOrder);

        $this->actingAs($agentUser)
            ->from(route('agent.active'))
            ->post(route('agent.status', $deliveredOrder->id), [
                'delivery_otp' => '123456',
            ])
            ->assertRedirect(route('agent.active'));

        $this->actingAs($agentUser)
            ->get(route('agent.active'))
            ->assertOk()
            ->assertSee('Delivery Completed!')
            ->assertDontSee('Customer cancelled this order.');
    }

    public function test_agent_can_complete_delivery_with_a_customer_qr_token(): void
    {
        $this->createAdmin();
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0, totalDeliveries: 0);
        $customer = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_OTP_VERIFICATION,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Fresh delivery order',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
            'delivery_otp' => '123456',
            'delivery_handoff_token' => 'handoff-token-123',
            'delivery_otp_generated_at' => now(),
        ]);

        app(BillingService::class)->finalizeBilling($order);

        $this->actingAs($agentUser)
            ->from(route('agent.active'))
            ->post(route('agent.status', $order->id), [
                'delivery_handoff_token' => 'handoff-token-123',
            ])
            ->assertRedirect(route('agent.active'));

        $this->assertSame(FuelRequest::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->delivery_handoff_token);
        $this->assertNotNull($order->fresh()->delivery_otp_verified_at);
    }

    public function test_completing_a_delivered_order_does_not_credit_wallets_twice(): void
    {
        $this->createAdmin();
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 85.38, totalDeliveries: 0);
        $customer = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => 'delivered',
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 603.55,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'delivery_address' => 'Near City Center',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
        ]);

        app(BillingService::class)->finalizeBilling($order);

        $this->actingAs($agentUser)
            ->from(route('agent.active'))
            ->post(route('agent.status', $order->id))
            ->assertRedirect(route('agent.active'));

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(85.38, $agent->fresh()->wallet_balance);
        $this->assertSame(1, $agent->fresh()->total_deliveries);
    }

    private function createAdmin(): User
    {
        return $this->createUser(role: 'admin');
    }

    private function createApprovedAgent(float $walletBalance = 0, int $totalDeliveries = 5): array
    {
        $user = $this->createUser(role: 'agent');

        $agent = Agent::query()->create([
            'user_id' => $user->id,
            'vehicle_type' => 'Bike',
            'vehicle_license_plate' => 'MH01AB1234',
            'approval_status' => 'approved',
            'verification_status' => 'approved',
            'approved_at' => now(),
            'is_available' => true,
            'wallet_balance' => $walletBalance,
            'total_deliveries' => $totalDeliveries,
        ]);

        return [$user, $agent];
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . uniqid() . '@example.com',
            'email_verified_at' => now(),
            'phone' => '900000' . random_int(1000, 9999),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function createFuelProduct(): FuelProduct
    {
        return FuelProduct::query()->create([
            'name' => 'Regular Petrol',
            'fuel_type' => 'petrol',
            'price_per_liter' => 102.50,
            'is_available' => true,
        ]);
    }
}
