<?php

namespace Tests\Feature;

use App\EmailOtpCodeMail;
use App\Models\FuelProduct;
use App\Models\FuelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderOtpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_and_verify_an_order_email_code(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson(route('user.send.otp'))
            ->assertOk()
            ->assertJson([
                'status' => true,
            ]);

        $otp = null;

        Mail::assertSent(EmailOtpCodeMail::class, function (EmailOtpCodeMail $mail) use (&$otp): bool {
            $otp = $mail->code;

            return $mail->purposeLabel === 'order confirmation';
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $otp);

        $this->actingAs($user)
            ->postJson(route('user.verify.otp'), [
                'otp' => $otp,
            ])
            ->assertOk()
            ->assertJson([
                'status' => true,
            ]);

        $this->assertNull(session('email_otp.order.pending'));
        $this->assertSame($user->email, session('email_otp.order.verified.email'));
    }

    public function test_user_cannot_place_an_order_without_a_verified_email_code(): void
    {
        $user = $this->createUser();
        $product = $this->createFuelProduct();

        $response = $this->actingAs($user)
            ->from(route('user.order'))
            ->post(route('user.order.place'), $this->validOrderPayload($product));

        $response
            ->assertRedirect(route('user.order'))
            ->assertSessionHas('error', 'Please verify the email code before placing your order.');

        $this->assertDatabaseCount('fuel_requests', 0);
    }

    public function test_user_can_place_an_order_after_successful_email_code_verification(): void
    {
        Mail::fake();
        $user = $this->createUser();
        $product = $this->createFuelProduct();

        $this->actingAs($user)->postJson(route('user.send.otp'))->assertOk();

        $otp = null;

        Mail::assertSent(EmailOtpCodeMail::class, function (EmailOtpCodeMail $mail) use (&$otp): bool {
            $otp = $mail->code;

            return true;
        });

        $this->actingAs($user)
            ->postJson(route('user.verify.otp'), [
                'otp' => $otp,
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
        $this->assertNull(session('email_otp.order.pending'));
        $this->assertNull(session('email_otp.order.verified'));
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => 'user' . uniqid() . '@example.com',
            'email_verified_at' => now(),
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
