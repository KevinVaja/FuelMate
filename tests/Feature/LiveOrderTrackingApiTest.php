<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveOrderTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_update_precise_live_location_via_api(): void
    {
        [$agentUser, $agent] = $this->createApprovedAgent();
        $customer = $this->createUser('user', 'Tracking Customer');
        $product = $this->createFuelProduct();

        FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_ON_THE_WAY,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 582.50,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Near Talala Circle',
            'delivery_lat' => 21.0502,
            'delivery_lng' => 70.5405,
            'distance_km' => 5.0,
            'booked_distance_km' => 5.0,
        ]);

        $response = $this->actingAs($agentUser)
            ->postJson(route('api.agent.location.update'), [
                'latitude' => 21.05371234,
                'longitude' => 70.51659876,
            ]);

        $response->assertOk()
            ->assertJsonPath('latitude', 21.05371234)
            ->assertJsonPath('longitude', 70.51659876);

        $agent->refresh();

        $this->assertSame(21.05371234, round((float) $agent->current_latitude, 8));
        $this->assertSame(70.51659876, round((float) $agent->current_longitude, 8));
        $this->assertSame(21.0537123, round((float) $agent->current_lat, 7));
        $this->assertSame(70.5165988, round((float) $agent->current_lng, 7));
        $this->assertNotNull($agent->last_location_update);
    }

    public function test_order_owner_can_fetch_live_tracking_payload_but_other_users_cannot(): void
    {
        $customer = $this->createUser('user', 'Tracking Customer');
        $otherCustomer = $this->createUser('user', 'Other Customer');
        [, $agent] = $this->createApprovedAgent([
            'current_lat' => 21.0537123,
            'current_lng' => 70.5165988,
            'current_latitude' => 21.05371234,
            'current_longitude' => 70.51659876,
            'last_location_update' => now(),
        ]);
        $product = $this->createFuelProduct();

        $order = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_ON_THE_WAY,
            'quantity_liters' => 8,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 890.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Talala Main Road',
            'delivery_lat' => 21.0502,
            'delivery_lng' => 70.5405,
            'distance_km' => 4.5,
            'booked_distance_km' => 4.5,
        ]);

        $this->actingAs($customer)
            ->getJson(route('api.order.track', $order->id))
            ->assertOk()
            ->assertJsonPath('status', FuelRequest::STATUS_ON_THE_WAY)
            ->assertJsonPath('tracking_enabled', true)
            ->assertJsonPath('agent_latitude', 21.05371234)
            ->assertJsonPath('agent_longitude', 70.51659876)
            ->assertJsonPath('user_latitude', 21.0502)
            ->assertJsonPath('user_longitude', 70.5405);

        $this->actingAs($otherCustomer)
            ->getJson(route('api.order.track', $order->id))
            ->assertNotFound();
    }

    public function test_user_tracking_page_shows_live_map_only_when_order_is_on_the_way(): void
    {
        $customer = $this->createUser('user', 'Tracking Customer');
        [, $agent] = $this->createApprovedAgent([
            'current_lat' => 21.0537123,
            'current_lng' => 70.5165988,
            'current_latitude' => 21.05371234,
            'current_longitude' => 70.51659876,
            'last_location_update' => now(),
        ]);
        $product = $this->createFuelProduct();

        $onTheWayOrder = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_ON_THE_WAY,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 582.50,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Talala Main Road',
            'delivery_lat' => 21.0502,
            'delivery_lng' => 70.5405,
            'distance_km' => 4.5,
            'booked_distance_km' => 4.5,
        ]);

        $acceptedOrder = FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $agent->id,
            'fuel_product_id' => $product->id,
            'status' => FuelRequest::STATUS_ACCEPTED,
            'quantity_liters' => 5,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 70,
            'total_amount' => 582.50,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Talala Main Road',
            'delivery_lat' => 21.0502,
            'delivery_lng' => 70.5405,
            'distance_km' => 4.5,
            'booked_distance_km' => 4.5,
        ]);

        $this->actingAs($customer)
            ->get(route('user.track', $onTheWayOrder->id))
            ->assertOk()
            ->assertSee('orderTrackingMap')
            ->assertSeeText('Auto-updates every');

        $this->actingAs($customer)
            ->get(route('user.track', $acceptedOrder->id))
            ->assertOk()
            ->assertDontSee('orderTrackingMap');
    }

    private function createApprovedAgent(array $attributes = []): array
    {
        $user = $this->createUser('agent', 'Tracking Agent');

        $agent = Agent::query()->create(array_merge([
            'user_id' => $user->id,
            'vehicle_type' => 'Petrol Pump Business',
            'vehicle_license_plate' => 'PUMP-TRACK',
            'approval_status' => 'approved',
            'verification_status' => Agent::VERIFICATION_APPROVED,
            'approved_at' => now(),
            'is_available' => true,
            'status' => Agent::STATUS_ACTIVE,
            'last_active_at' => now(),
        ], $attributes));

        return [$user, $agent];
    }

    private function createUser(string $role, string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '.' . uniqid() . '@example.com',
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
