<?php

namespace Tests\Feature;

use App\EmailOtpCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_a_verified_email_address(): void
    {
        $response = $this->from(route('register'))
            ->post(route('register'), [
                'name' => 'New Customer',
                'email' => 'new-customer@example.com',
                'phone' => '+91 9876543210',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => 'user',
            ]);

        $response
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'email' => 'Verify this email address before creating the account.',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_can_register_after_email_code_verification(): void
    {
        Mail::fake();

        $email = 'verified-user@example.com';

        $this->postJson(route('register.email_otp.send'), [
            'email' => $email,
        ])->assertOk();

        $otp = null;

        Mail::assertSent(EmailOtpCodeMail::class, function (EmailOtpCodeMail $mail) use (&$otp): bool {
            $otp = $mail->code;

            return $mail->purposeLabel === 'registration';
        });

        $this->postJson(route('register.email_otp.verify'), [
            'email' => $email,
            'otp' => $otp,
        ])->assertOk();

        $response = $this->post(route('register'), [
            'name' => 'Verified Customer',
            'email' => $email,
            'phone' => '+91 9876543210',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'user',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'role' => 'user',
            'status' => 'active',
        ]);
        $this->assertNotNull(User::query()->where('email', $email)->firstOrFail()->email_verified_at);
        $this->assertNull(session('email_otp.registration.pending'));
        $this->assertNull(session('email_otp.registration.verified'));
    }
}
