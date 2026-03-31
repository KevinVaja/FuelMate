<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_endpoint_returns_a_gst_ready_bill(): void
    {
        $user = $this->createUser(role: 'user');
        $this->createApprovedAgent();
        $product = $this->createFuelProduct();

        $this->actingAs($user)
            ->postJson(route('user.order.estimate'), [
                'fuel_product_id' => $product->id,
                'quantity_liters' => 5,
                'delivery_lat' => 19.0900,
                'delivery_lng' => 72.8777,
                'payment_method' => 'online',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.slab_charge', 40)
            ->assertJsonPath('data.night_fee', 0)
            ->assertJsonPath('data.delivery_charge', 40)
            ->assertJsonPath('data.platform_fee', 10)
            ->assertJsonPath('data.gst_amount', 101.25)
            ->assertJsonPath('data.total_amount', 663.75);
    }

    public function test_estimate_endpoint_adds_night_delivery_extra_during_night_hours(): void
    {
        Carbon::setTestNow('2026-03-16 23:30:00');

        $user = $this->createUser(role: 'user');
        $this->createApprovedAgent();
        $product = $this->createFuelProduct();

        $this->actingAs($user)
            ->postJson(route('user.order.estimate'), [
                'fuel_product_id' => $product->id,
                'quantity_liters' => 5,
                'delivery_lat' => 19.0900,
                'delivery_lng' => 72.8777,
                'payment_method' => 'online',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.slab_charge', 40)
            ->assertJsonPath('data.night_fee', 20)
            ->assertJsonPath('data.delivery_charge', 60)
            ->assertJsonPath('data.gst_amount', 104.85)
            ->assertJsonPath('data.total_amount', 687.35);
    }

    public function test_estimate_uses_nearby_agent_even_if_that_agent_has_past_delivered_orders(): void
    {
        $user = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();
        $nearbyAgent = $this->createApprovedAgent(lat: 21.5306, lng: 70.4585);
        $farAgent = $this->createApprovedAgent(lat: 22.6213, lng: 70.2934);

        FuelRequest::query()->create([
            'user_id' => $user->id,
            'agent_id' => $nearbyAgent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_DELIVERED,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 603.55,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'delivery_address' => 'Past order',
            'location_mode' => 'live_gps',
            'delivery_lat' => 21.5306,
            'delivery_lng' => 70.4585,
            'distance_km' => 1.0,
            'booked_distance_km' => 1.0,
        ]);

        $this->actingAs($user)
            ->postJson(route('user.order.estimate'), [
                'fuel_product_id' => $product->id,
                'quantity_liters' => 5,
                'delivery_lat' => 21.5310,
                'delivery_lng' => 70.4590,
                'payment_method' => 'online',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJson(fn ($json) => $json->where('data.distance_km', fn ($distance) => $distance < 1)->etc());
    }

    public function test_estimate_allows_very_close_delivery_distance(): void
    {
        $user = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();
        $this->createApprovedAgent(lat: 21.5306, lng: 70.4585);

        $this->actingAs($user)
            ->postJson(route('user.order.estimate'), [
                'fuel_product_id' => $product->id,
                'quantity_liters' => 5,
                'delivery_lat' => 21.5309,
                'delivery_lng' => 70.4589,
                'payment_method' => 'online',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.distance_km', 0.05);
    }

    public function test_customer_can_open_invoice_page_for_order_with_billing(): void
    {
        $user = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $user->id,
            'fuel_product_id' => $product->id,
            'status' => 'pending',
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'wallet',
            'payment_status' => 'paid',
            'delivery_address' => 'Near City Center',
            'location_mode' => 'live_gps',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
        ]);

        app(BillingService::class)->createEstimatedBilling($order, 5.0);

        $this->actingAs($user)
            ->get(route('orders.invoice', $order->id))
            ->assertOk()
            ->assertSee('Invoice for Order #' . $order->id)
            ->assertSee('Wallet')
            ->assertSee('Billing Breakdown');
    }

    public function test_customer_can_download_invoice_pdf_for_order_with_billing(): void
    {
        $user = $this->createUser(role: 'user');
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $user->id,
            'fuel_product_id' => $product->id,
            'status' => 'pending',
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'payment_method' => 'wallet',
            'payment_status' => 'paid',
            'delivery_address' => 'Near City Center',
            'location_mode' => 'live_gps',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
        ]);

        app(BillingService::class)->createEstimatedBilling($order, 5.0);

        $response = $this->actingAs($user)
            ->get(route('orders.invoice.download', $order->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-1.4', (string) $response->getContent());
        $this->assertStringContainsString('FuelMate Invoice', (string) $response->getContent());
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

    private function createApprovedAgent(float $lat = 19.0760, float $lng = 72.8777): Agent
    {
        $agentUser = $this->createUser(role: 'agent');

        return Agent::query()->create([
            'user_id' => $agentUser->id,
            'vehicle_type' => 'Bike',
            'vehicle_license_plate' => 'MH01AB1234',
            'approval_status' => 'approved',
            'verification_status' => 'approved',
            'approved_at' => now(),
            'is_available' => true,
            'wallet_balance' => 0,
            'current_lat' => $lat,
            'current_lng' => $lng,
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
