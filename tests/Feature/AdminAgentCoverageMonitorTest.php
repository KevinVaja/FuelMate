<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAgentCoverageMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_agent_coverage_monitor_and_its_live_summary(): void
    {
        $admin = $this->createUser('admin', 'Coverage Admin');
        $customer = $this->createUser('user', 'Coverage Customer');
        $fuelProduct = $this->createFuelProduct();

        $activeAgent = $this->createApprovedAgent('Talala Active Pump', [
            'is_available' => true,
            'status' => Agent::STATUS_ACTIVE,
            'last_active_at' => now(),
            'pump_latitude' => 21.0537,
            'pump_longitude' => 70.5165,
            'current_lat' => 21.0541,
            'current_lng' => 70.5169,
        ]);

        $busyAgent = $this->createApprovedAgent('Talala Busy Pump', [
            'is_available' => true,
            'status' => Agent::STATUS_BUSY,
            'last_active_at' => now(),
            'pump_latitude' => 21.0418,
            'pump_longitude' => 70.5312,
            'current_lat' => 21.0471,
            'current_lng' => 70.5388,
        ]);

        $offlineAgent = $this->createApprovedAgent('Talala Offline Pump', [
            'is_available' => false,
            'status' => Agent::STATUS_OFFLINE,
            'last_active_at' => now()->subHours(2),
            'pump_latitude' => 21.0724,
            'pump_longitude' => 70.5011,
            'current_lat' => 21.0724,
            'current_lng' => 70.5011,
        ]);

        FuelRequest::query()->create([
            'user_id' => $customer->id,
            'agent_id' => $busyAgent->id,
            'fuel_product_id' => $fuelProduct->id,
            'status' => FuelRequest::STATUS_ON_THE_WAY,
            'quantity_liters' => 8,
            'fuel_price_per_liter' => 102.50,
            'delivery_charge' => 80,
            'total_amount' => 900,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_address' => 'Talala Main Road',
            'delivery_lat' => 21.0502,
            'delivery_lng' => 70.5405,
            'distance_km' => 4.5,
            'booked_distance_km' => 4.5,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.agent_coverage'))
            ->assertOk()
            ->assertSee('Agent Coverage & Activity Monitor')
            ->assertSee('Talala Busy Pump');

        $this->actingAs($admin)
            ->getJson(route('admin.agent_coverage.data'))
            ->assertOk()
            ->assertJsonPath('summary.total_agents', 3)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.busy', 1)
            ->assertJsonPath('summary.offline', 1)
            ->assertJsonPath('agents.0.marker_location_label', 'Petrol pump location')
            ->assertJsonPath('agents.0.marker_location.lat', 21.0537)
            ->assertJsonPath('agents.0.marker_location.lng', 70.5165)
            ->assertJsonCount(3, 'agents')
            ->assertJsonFragment([
                'agent_name' => 'Talala Active Pump',
                'status' => Agent::STATUS_ACTIVE,
            ])
            ->assertJsonFragment([
                'agent_name' => 'Talala Busy Pump',
                'status' => Agent::STATUS_BUSY,
                'marker_location_label' => 'Live delivery position',
            ])
            ->assertJsonFragment([
                'agent_name' => 'Talala Offline Pump',
                'status' => Agent::STATUS_OFFLINE,
            ]);
    }

    public function test_admin_can_filter_agent_coverage_by_status(): void
    {
        $admin = $this->createUser('admin', 'Coverage Admin');
        $this->createApprovedAgent('Talala Active Pump', [
            'is_available' => true,
            'status' => Agent::STATUS_ACTIVE,
            'last_active_at' => now(),
            'pump_latitude' => 21.0537,
            'pump_longitude' => 70.5165,
            'current_lat' => 21.0541,
            'current_lng' => 70.5169,
        ]);

        $this->createApprovedAgent('Talala Offline Pump', [
            'is_available' => false,
            'status' => Agent::STATUS_OFFLINE,
            'last_active_at' => now()->subHours(2),
            'pump_latitude' => 21.0724,
            'pump_longitude' => 70.5011,
            'current_lat' => 21.0724,
            'current_lng' => 70.5011,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.agent_coverage.data', ['status' => Agent::STATUS_OFFLINE]))
            ->assertOk()
            ->assertJsonPath('summary.total_agents', 1)
            ->assertJsonCount(1, 'agents')
            ->assertJsonPath('agents.0.agent_name', 'Talala Offline Pump')
            ->assertJsonPath('agents.0.status', Agent::STATUS_OFFLINE);
    }

    public function test_admin_can_correct_a_pump_location_from_the_monitor(): void
    {
        $admin = $this->createUser('admin', 'Coverage Admin');
        $agent = $this->createApprovedAgent('Talala Active Pump', [
            'is_available' => true,
            'status' => Agent::STATUS_ACTIVE,
            'last_active_at' => now(),
            'pump_latitude' => 21.0537,
            'pump_longitude' => 70.5165,
            'current_lat' => 21.4001,
            'current_lng' => 70.9002,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.agents.pump_location.update', $agent->id), [
                'pump_latitude' => 21.0601,
                'pump_longitude' => 70.5219,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'pump_latitude' => 21.0601,
            'pump_longitude' => 70.5219,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.agent_coverage.data'))
            ->assertOk()
            ->assertJsonFragment([
                'agent_name' => 'Talala Active Pump',
                'marker_location_label' => 'Petrol pump location',
            ])
            ->assertJsonFragment([
                'lat' => 21.0601,
                'lng' => 70.5219,
            ]);
    }

    private function createApprovedAgent(string $name, array $attributes = []): Agent
    {
        $user = $this->createUser('agent', $name);

        return Agent::query()->create(array_merge([
            'user_id' => $user->id,
            'vehicle_type' => 'Petrol Pump Business',
            'vehicle_license_plate' => 'PUMP-001',
            'approval_status' => 'approved',
            'verification_status' => Agent::VERIFICATION_APPROVED,
            'approved_at' => now(),
            'is_available' => false,
            'status' => Agent::STATUS_OFFLINE,
            'last_active_at' => now()->subHour(),
        ], $attributes));
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
