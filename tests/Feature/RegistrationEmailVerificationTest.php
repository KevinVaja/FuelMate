<?php

namespace Tests\Feature;

use App\EmailOtpCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
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

    public function test_registration_otp_send_fails_cleanly_when_production_mail_is_not_configured(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'log',
        ]);

        $response = $this->postJson(route('register.email_otp.send'), [
            'email' => 'mail-check@example.com',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'status' => false,
                'message' => 'Email delivery is not configured on the server yet. Please set the Render mail environment variables and deploy again.',
            ]);

        $this->assertNull(session('email_otp.registration.pending'));
    }

    public function test_registration_otp_send_surfaces_smtp_auth_failures_cleanly(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'fuelmate.web@gmail.com',
            'mail.mailers.smtp.password' => 'app-password',
            'mail.from.address' => 'fuelmate.web@gmail.com',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('mail-check@example.com')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Expected response code "535" but got code "535", with message "535-5.7.8 Username and Password not accepted".'));

        $response = $this->postJson(route('register.email_otp.send'), [
            'email' => 'mail-check@example.com',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'status' => false,
                'message' => 'The server could not sign in to Gmail SMTP. Check the Render MAIL_USERNAME and Gmail App Password, then deploy again.',
            ]);

        $this->assertNull(session('email_otp.registration.pending'));
    }

    public function test_registration_otp_send_fails_cleanly_when_resend_is_not_configured(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'resend',
            'services.resend.key' => null,
            'mail.from.address' => null,
        ]);

        $response = $this->postJson(route('register.email_otp.send'), [
            'email' => 'mail-check@example.com',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'status' => false,
                'message' => 'Email delivery is not fully configured on the server yet. Set RESEND_API_KEY and MAIL_FROM_ADDRESS on Render, then deploy again.',
            ]);

        $this->assertNull(session('email_otp.registration.pending'));
    }

    public function test_registration_otp_send_surfaces_resend_domain_restrictions_cleanly(): void
    {
        config([
            'app.env' => 'production',
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_123',
            'mail.from.address' => 'onboarding@resend.dev',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('mail-check@example.com')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('403 You can only send testing emails to your own email address when using onboarding@resend.dev. Please verify a domain to send to other recipients.'));

        $response = $this->postJson(route('register.email_otp.send'), [
            'email' => 'mail-check@example.com',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'status' => false,
                'message' => 'Resend is connected, but the sender is not ready for public delivery yet. Use onboarding@resend.dev only for testing to your own email, or verify a domain in Resend and use that address as MAIL_FROM_ADDRESS.',
            ]);

        $this->assertNull(session('email_otp.registration.pending'));
    }
}
