<?php

namespace Tests\Feature;

use App\Models\AdminAccount;
use App\Models\Agent;
use App\Models\Billing;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use App\Services\AdminWalletService;
use App\Services\BillingService;
use App\Services\OrderCancellationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderCancellationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_online_order_moves_to_refund_processing_and_refunds_after_admin_approval(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user', walletBalance: 100);
        $product = $this->createFuelProduct();

        $order = $this->makeOrder($customer, $product, [
            'status' => FuelRequest::STATUS_PENDING,
            'payment_method' => 'online',
            'payment_status' => 'paid',
            'agent_id' => null,
        ]);

        $billing = app(BillingService::class)->createEstimatedBilling($order, 5.0);

        $cancelledOrder = app(OrderCancellationService::class)->cancelOrder(
            $order,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Need to cancel before dispatch.'
        );

        $this->assertSame(FuelRequest::STATUS_REFUND_PROCESSING, $cancelledOrder->status);
        $this->assertTrue($cancelledOrder->is_cancelled);
        $this->assertSame(0.0, (float) $cancelledOrder->cancellation_charge);
        $this->assertSame(Billing::REFUND_PENDING, $cancelledOrder->billing->refund_status);
        $this->assertSame((float) $billing->total_amount, (float) $cancelledOrder->billing->refundable_amount);
        $this->assertSame(100.0, (float) $customer->fresh()->wallet_balance);
        $this->assertSame(5000.0, (float) AdminAccount::query()->firstOrFail()->wallet_balance);

        $refundedOrder = app(OrderCancellationService::class)->approveRefund($cancelledOrder);

        $this->assertSame(FuelRequest::STATUS_CANCELLED, $refundedOrder->status);
        $this->assertSame(Billing::REFUND_REFUNDED, $refundedOrder->billing->refund_status);
        $this->assertSame(0.0, (float) $refundedOrder->billing->refundable_amount);
        $this->assertSame((float) $billing->total_amount, (float) $refundedOrder->billing->refunded_amount);
        $this->assertSame(
            100.0 + (float) $billing->total_amount,
            (float) $customer->fresh()->wallet_balance
        );
        $this->assertSame(
            5000.0 - (float) $billing->total_amount,
            (float) AdminAccount::query()->firstOrFail()->wallet_balance
        );
    }

    public function test_accepted_wallet_order_cancellation_applies_delivery_charge_and_agent_compensation(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user', walletBalance: 50);
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0);
        $product = $this->createFuelProduct();

        $order = $this->makeOrder($customer, $product, [
            'agent_id' => $agent->id,
            'status' => FuelRequest::STATUS_ACCEPTED,
            'payment_method' => 'wallet',
            'payment_status' => 'paid',
        ]);

        $billing = app(BillingService::class)->finalizeBilling($order);

        $cancelledOrder = app(OrderCancellationService::class)->cancelOrder(
            $order,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Cancelling before dispatch starts.'
        );

        $expectedAgentCompensation = round((float) $billing->delivery_charge * 0.30, 2);
        $expectedRefund = round((float) $billing->total_amount - (float) $billing->delivery_charge, 2);

        $this->assertSame(FuelRequest::STATUS_REFUND_PROCESSING, $cancelledOrder->status);
        $this->assertSame((float) $billing->delivery_charge, (float) $cancelledOrder->cancellation_charge);
        $this->assertSame($expectedRefund, (float) $cancelledOrder->billing->refundable_amount);
        $this->assertSame(Billing::REFUND_PENDING, $cancelledOrder->billing->refund_status);
        $this->assertSame($expectedAgentCompensation, (float) $cancelledOrder->billing->agent_earning);
        $this->assertSame($expectedAgentCompensation, (float) $agent->fresh()->wallet_balance);
        $this->assertSame(5000.0 - $expectedAgentCompensation, (float) AdminAccount::query()->firstOrFail()->wallet_balance);
        $this->assertSame($agentUser->id, $agent->user->id);
    }

    public function test_delivered_orders_cannot_be_cancelled(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user');
        [$agentUser, $agent] = $this->createApprovedAgent();
        $product = $this->createFuelProduct();

        $order = $this->makeOrder($customer, $product, [
            'agent_id' => $agent->id,
            'status' => FuelRequest::STATUS_DELIVERED,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        app(BillingService::class)->finalizeBilling($order);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Delivered orders cannot be cancelled.');

        app(OrderCancellationService::class)->cancelOrder(
            $order,
            FuelRequest::CANCELLED_BY_ADMIN,
            'Trying to cancel after delivery.'
        );
    }

    public function test_customer_must_settle_cod_cancellation_charge_before_direct_cancellation(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user');
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0);
        $product = $this->createFuelProduct();

        $order = $this->makeOrder($customer, $product, [
            'agent_id' => $agent->id,
            'status' => FuelRequest::STATUS_ACCEPTED,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        app(BillingService::class)->finalizeBilling($order);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pay the cancellation fee first, then the COD order will be cancelled.');

        app(OrderCancellationService::class)->cancelOrder(
            $order,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Trying to cancel a COD order after agent acceptance.'
        );
    }

    public function test_cod_customer_can_pay_cancellation_charge_and_cancel_order(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user', walletBalance: 10);
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0);
        $product = $this->createFuelProduct();

        $order = $this->makeOrder($customer, $product, [
            'agent_id' => $agent->id,
            'status' => FuelRequest::STATUS_ACCEPTED,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $billing = app(BillingService::class)->finalizeBilling($order);

        $cancelledOrder = app(OrderCancellationService::class)->payCancellationChargeAndCancel(
            $order,
            'Customer paid the COD cancellation charge.',
            FuelRequest::CANCELLATION_CHARGE_METHOD_ONLINE,
        );

        $expectedAgentCompensation = round((float) $billing->delivery_charge * 0.30, 2);
        $expectedRefundableAmount = round((float) $billing->total_amount - (float) $billing->delivery_charge, 2);

        $this->assertSame(FuelRequest::STATUS_CANCELLED, $cancelledOrder->status);
        $this->assertTrue($cancelledOrder->is_cancelled);
        $this->assertSame((float) $billing->delivery_charge, (float) $cancelledOrder->cancellation_charge);
        $this->assertSame($expectedRefundableAmount, (float) $cancelledOrder->billing->refundable_amount);
        $this->assertSame(Billing::REFUND_NONE, $cancelledOrder->billing->refund_status);
        $this->assertSame(FuelRequest::CANCELLATION_CHARGE_PAYMENT_PAID, $cancelledOrder->cancellation_charge_payment_status);
        $this->assertSame(FuelRequest::CANCELLATION_CHARGE_METHOD_ONLINE, $cancelledOrder->cancellation_charge_payment_method);
        $this->assertNotEmpty($cancelledOrder->cancellation_charge_payment_reference);
        $this->assertSame($expectedAgentCompensation, (float) $agent->fresh()->wallet_balance);
        $this->assertSame(
            5000.0 + (float) $billing->delivery_charge - $expectedAgentCompensation,
            (float) AdminAccount::query()->firstOrFail()->wallet_balance
        );
        $this->assertSame($agentUser->id, $agent->user->id);
    }

    public function test_auto_cancel_command_cancels_stale_pending_and_accepted_orders(): void
    {
        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user');
        [$agentUser, $agent] = $this->createApprovedAgent(walletBalance: 0);
        $product = $this->createFuelProduct();

        $pendingOrder = $this->makeOrder($customer, $product, [
            'status' => FuelRequest::STATUS_PENDING,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'agent_id' => null,
            'created_at' => now()->subMinutes(6),
            'updated_at' => now()->subMinutes(6),
        ]);
        app(BillingService::class)->createEstimatedBilling($pendingOrder, 5.0);

        $acceptedOrder = $this->makeOrder($customer, $product, [
            'status' => FuelRequest::STATUS_ACCEPTED,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'agent_id' => $agent->id,
            'agent_last_movement_at' => now()->subMinutes(11),
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ]);
        app(BillingService::class)->finalizeBilling($acceptedOrder);

        $freshAcceptedOrder = $this->makeOrder($customer, $product, [
            'status' => FuelRequest::STATUS_ACCEPTED,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'agent_id' => $agent->id,
            'agent_last_movement_at' => now()->subMinutes(2),
        ]);
        app(BillingService::class)->finalizeBilling($freshAcceptedOrder);

        $this->artisan('orders:auto-cancel')->assertSuccessful();

        $this->assertSame(FuelRequest::STATUS_CANCELLED, $pendingOrder->fresh()->status);
        $this->assertSame(FuelRequest::STATUS_CANCELLED, $acceptedOrder->fresh()->status);
        $this->assertSame(FuelRequest::STATUS_ACCEPTED, $freshAcceptedOrder->fresh()->status);
        $this->assertSame(21.0, (float) $agent->fresh()->wallet_balance);
        $this->assertSame(4979.0, (float) AdminAccount::query()->firstOrFail()->wallet_balance);
        $this->assertSame(FuelRequest::CANCELLED_BY_SYSTEM, $pendingOrder->fresh()->cancelled_by);
    }

    public function test_customer_fraud_protection_blocks_excessive_recent_cancellations(): void
    {
        config([
            'cancellation.fraud.customer_max_cancellations' => 1,
            'cancellation.fraud.window_minutes' => 1440,
        ]);

        $this->createAdminWithWallet(5000);
        $customer = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        FuelRequest::query()->create([
            'user_id' => $customer->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_CANCELLED,
            'is_cancelled' => true,
            'cancelled_by' => FuelRequest::CANCELLED_BY_CUSTOMER,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 699.15,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Existing cancelled order',
            'location_mode' => 'map_pin',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
            'cancelled_at' => now()->subHour(),
            'cancellation_reason' => 'Previous cancellation',
            'cancellation_charge' => 0,
        ]);

        $newOrder = $this->makeOrder($customer, $product, [
            'status' => FuelRequest::STATUS_PENDING,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'agent_id' => null,
        ]);
        app(BillingService::class)->createEstimatedBilling($newOrder, 5.0);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Too many recent customer cancellations were detected on your account. Please contact support to continue.');

        app(OrderCancellationService::class)->cancelOrder(
            $newOrder,
            FuelRequest::CANCELLED_BY_CUSTOMER,
            'Trying to cancel again too soon.'
        );
    }

    private function createAdminWithWallet(float $walletBalance): User
    {
        $admin = $this->createUser(role: 'admin');

        app(AdminWalletService::class)->resolvePrimaryAdminAccount()->update([
            'wallet_balance' => $walletBalance,
        ]);

        return $admin;
    }

    private function createApprovedAgent(float $walletBalance = 0): array
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
            'current_lat' => 19.0760,
            'current_lng' => 72.8777,
        ]);

        return [$user, $agent];
    }

    private function createUser(string $role, float $walletBalance = 0): User
    {
        return User::query()->create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . uniqid() . '@example.com',
            'phone' => '900000' . random_int(1000, 9999),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'wallet_balance' => $walletBalance,
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

    private function makeOrder(User $customer, FuelProduct $product, array $overrides = []): FuelRequest
    {
        $attributes = array_merge([
            'user_id' => $customer->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_PENDING,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Near City Center',
            'location_mode' => 'live_gps',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'estimated_delivery_minutes' => 15,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
            'agent_last_movement_at' => null,
        ], $overrides);

        $timestampOverrides = array_intersect_key($attributes, array_flip(['created_at', 'updated_at']));

        unset($attributes['created_at'], $attributes['updated_at']);

        $order = FuelRequest::query()->create($attributes);

        if ($timestampOverrides !== []) {
            $order->forceFill($timestampOverrides)->saveQuietly();
        }

        return $order->fresh();
    }
}
