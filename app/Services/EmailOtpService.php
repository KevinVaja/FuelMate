<?php

namespace App\Services;

use App\EmailOtpCodeMail;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailOtpService
{
    public const PURPOSE_REGISTRATION = 'registration';
    public const PURPOSE_ORDER = 'order';

    private const MAX_ATTEMPTS = 5;

    public function sendCode(
        Request $request,
        string $purpose,
        string $email,
        string $purposeLabel,
        int $ttlMinutes,
        ?string $recipientName = null,
    ): void {
        $this->ensureProductionMailerIsReady();

        $normalizedEmail = $this->normalizeEmail($email);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put($this->pendingKey($purpose), [
            'email' => $normalizedEmail,
            'otp_hash' => $this->hashOtp($purpose, $normalizedEmail, $code),
            'sent_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
            'ttl_minutes' => $ttlMinutes,
            'attempts' => 0,
        ]);

        $request->session()->forget($this->verifiedKey($purpose));

        try {
            Mail::to($normalizedEmail)->send(
                new EmailOtpCodeMail(
                    code: $code,
                    purposeLabel: $purposeLabel,
                    ttlMinutes: $ttlMinutes,
                    recipientName: $recipientName,
                )
            );
        } catch (Throwable $exception) {
            $request->session()->forget($this->pendingKey($purpose));
            report($exception);

            throw new DomainException($this->smtpFailureMessage($exception));
        }
    }

    public function verifyCode(Request $request, string $purpose, string $email, string $otp): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $pending = $request->session()->get($this->pendingKey($purpose));

        if (! is_array($pending) || $pending === []) {
            return [
                'status' => false,
                'message' => 'Please request a fresh email code before continuing.',
            ];
        }

        if (($pending['email'] ?? null) !== $normalizedEmail) {
            return [
                'status' => false,
                'message' => 'The email address changed. Request a new code for the current email address.',
            ];
        }

        if ($this->isExpired($pending['expires_at'] ?? null)) {
            $request->session()->forget($this->pendingKey($purpose));

            return [
                'status' => false,
                'message' => 'The verification code expired. Please request a new one and try again.',
            ];
        }

        $attempts = (int) ($pending['attempts'] ?? 0) + 1;

        if (! hash_equals((string) ($pending['otp_hash'] ?? ''), $this->hashOtp($purpose, $normalizedEmail, trim($otp)))) {
            if ($attempts >= self::MAX_ATTEMPTS) {
                $request->session()->forget($this->pendingKey($purpose));

                return [
                    'status' => false,
                    'message' => 'Too many invalid attempts. Please request a new verification code.',
                ];
            }

            $pending['attempts'] = $attempts;
            $request->session()->put($this->pendingKey($purpose), $pending);

            return [
                'status' => false,
                'message' => 'Invalid verification code. Please check the code and try again.',
            ];
        }

        $ttlMinutes = max(1, (int) ($pending['ttl_minutes'] ?? 5));

        $request->session()->put($this->verifiedKey($purpose), [
            'email' => $normalizedEmail,
            'verified_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
        ]);

        $request->session()->forget($this->pendingKey($purpose));

        return [
            'status' => true,
            'message' => 'Email verified successfully.',
        ];
    }

    public function hasFreshVerification(Request $request, string $purpose, string $email): bool
    {
        $verified = $request->session()->get($this->verifiedKey($purpose));
        $normalizedEmail = $this->normalizeEmail($email);

        if (! is_array($verified) || $verified === []) {
            return false;
        }

        if (($verified['email'] ?? null) !== $normalizedEmail) {
            return false;
        }

        if ($this->isExpired($verified['expires_at'] ?? null)) {
            $request->session()->forget($this->verifiedKey($purpose));

            return false;
        }

        return true;
    }

    public function verifiedEmail(Request $request, string $purpose): ?string
    {
        $verified = $request->session()->get($this->verifiedKey($purpose));

        if (! is_array($verified) || $verified === []) {
            return null;
        }

        if ($this->isExpired($verified['expires_at'] ?? null)) {
            $request->session()->forget($this->verifiedKey($purpose));

            return null;
        }

        $email = trim((string) ($verified['email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    public function clearState(Request $request, string $purpose): void
    {
        $request->session()->forget([
            $this->pendingKey($purpose),
            $this->verifiedKey($purpose),
        ]);
    }

    public function maskEmail(string $email): string
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if (! str_contains($normalizedEmail, '@')) {
            return $normalizedEmail;
        }

        [$localPart, $domain] = explode('@', $normalizedEmail, 2);
        $visiblePrefix = Str::substr($localPart, 0, min(2, strlen($localPart)));
        $maskedLocal = $visiblePrefix . str_repeat('*', max(strlen($localPart) - strlen($visiblePrefix), 2));

        return "{$maskedLocal}@{$domain}";
    }

    private function pendingKey(string $purpose): string
    {
        return "email_otp.{$purpose}.pending";
    }

    private function verifiedKey(string $purpose): string
    {
        return "email_otp.{$purpose}.verified";
    }

    private function hashOtp(string $purpose, string $email, string $otp): string
    {
        return hash('sha256', implode('|', [
            $purpose,
            $email,
            trim($otp),
            (string) config('app.key'),
        ]));
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function ensureProductionMailerIsReady(): void
    {
        if ((string) config('app.env') !== 'production') {
            return;
        }

        $defaultMailer = (string) config('mail.default', 'log');

        if (in_array($defaultMailer, ['log', 'array'], true)) {
            throw new DomainException(
                'Email delivery is not configured on the server yet. Please set the Render mail environment variables and deploy again.'
            );
        }

        if ($defaultMailer !== 'smtp') {
            return;
        }

        $smtpHost = trim((string) config('mail.mailers.smtp.host'));
        $smtpUsername = trim((string) config('mail.mailers.smtp.username'));
        $smtpPassword = trim((string) config('mail.mailers.smtp.password'));
        $fromAddress = trim((string) config('mail.from.address'));

        $smtpLooksPlaceholder = $smtpHost === ''
            || in_array(Str::lower($smtpHost), ['127.0.0.1', 'localhost'], true)
            || $smtpUsername === ''
            || $smtpPassword === ''
            || $fromAddress === ''
            || $fromAddress === 'hello@example.com';

        if ($smtpLooksPlaceholder) {
            throw new DomainException(
                'Email delivery is not fully configured on the server yet. Please complete the Render SMTP settings and deploy again.'
            );
        }
    }

    private function smtpFailureMessage(Throwable $exception): string
    {
        $message = Str::lower(trim($exception->getMessage()));

        if ($message !== '') {
            if (
                str_contains($message, '535')
                || str_contains($message, 'authentication')
                || str_contains($message, 'auth')
                || str_contains($message, 'username')
                || str_contains($message, 'password')
            ) {
                return 'The server could not sign in to Gmail SMTP. Check the Render MAIL_USERNAME and Gmail App Password, then deploy again.';
            }

            if (
                str_contains($message, 'connection could not be established')
                || str_contains($message, 'connection timed out')
                || str_contains($message, 'stream_socket_client')
                || str_contains($message, 'network is unreachable')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'could not connect')
            ) {
                return 'The server could not connect to Gmail SMTP. Check MAIL_HOST, MAIL_PORT, MAIL_SCHEME, and then redeploy the service.';
            }
        }

        return 'The server could not send the email right now. Please check the Render mail settings and try again.';
    }

    private function isExpired(mixed $timestamp): bool
    {
        if (! is_string($timestamp) || $timestamp === '') {
            return true;
        }

        return Carbon::parse($timestamp)->isPast();
    }
}
