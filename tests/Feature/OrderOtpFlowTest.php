<?php

namespace Tests\Feature;

use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_verify_an_order_otp(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('user.send.otp'))
            ->assertOk()
            ->assertJson([
                'status' => true,
            ]);

        $otp = session('order_otp.value');

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $otp);

        $this->actingAs($user)
            ->postJson(route('user.verify.otp'), [
                'otp' => $otp,
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
            ]);

        $this->assertNull(session('order_otp.value'));
        $this->assertNotNull(session('order_otp.verified_at'));
    }

    public function test_user_cannot_place_an_order_without_a_verified_otp(): void
    {
        $user = $this->createUser();
        $product = $this->createFuelProduct();

        $response = $this->actingAs($user)
            ->from(route('user.order'))
            ->post(route('user.order.place'), $this->validOrderPayload($product));

        $response
            ->assertRedirect(route('user.order'))
            ->assertSessionHas('error', 'Please verify the OTP before placing your order.');

        $this->assertDatabaseCount('fuel_requests', 0);
    }

    public function test_user_can_place_an_order_after_successful_otp_verification(): void
    {
        $user = $this->createUser();
        $product = $this->createFuelProduct();

        $this->actingAs($user)->postJson(route('user.send.otp'))->assertOk();

        $this->actingAs($user)
            ->postJson(route('user.verify.otp'), [
                'otp' => session('order_otp.value'),
            ])
            ->assertOk();

        $response = $this->actingAs($user)
            ->post(route('user.order.place'), $this->validOrderPayload($product));

        $order = FuelRequest::query()->first();

        $response->assertRedirect(route('user.track', $order));

        $this->assertNotNull($order);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertDatabaseHas('billings', [
            'order_id' => $order->id,
            'billing_status' => 'estimated',
        ]);
        $this->assertNull(session('order_otp.verified_at'));
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
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

    private function validOrderPayload(FuelProduct $product): array
    {
        return [
            'fuel_product_id' => $product->id,
            'quantity_liters' => 5,
            'delivery_address' => 'Near City Center',
            'location_mode' => 'live_gps',
            'delivery_lat' => 19.0760,
            'delivery_lng' => 72.8777,
            'payment_method' => 'cod',
            'notes' => 'Please call on arrival.',
        ];
    }
}
