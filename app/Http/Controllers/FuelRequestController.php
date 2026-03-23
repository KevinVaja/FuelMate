<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FuelRequestController extends Controller
{
    private const OTP_SESSION_KEY = 'order_otp.value';
    private const OTP_GENERATED_AT_SESSION_KEY = 'order_otp.generated_at';
    private const OTP_VERIFIED_AT_SESSION_KEY = 'order_otp.verified_at';
    private const OTP_TTL_MINUTES = 5;

    public function sendOtp()
    {
        $otp = (string) random_int(100000, 999999);

        session([
            self::OTP_SESSION_KEY => $otp,
            self::OTP_GENERATED_AT_SESSION_KEY => now()->toIso8601String(),
            self::OTP_VERIFIED_AT_SESSION_KEY => null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'OTP generated successfully. Enter it below to place your order.',
            'otp' => $otp, // Returned only for the current demo flow.
            'expires_in_seconds' => self::OTP_TTL_MINUTES * 60,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $expectedOtp = (string) session(self::OTP_SESSION_KEY, '');
        $generatedAt = session(self::OTP_GENERATED_AT_SESSION_KEY);

        if ($expectedOtp === '' || ! $generatedAt) {
            return response()->json([
                'status' => false,
                'message' => 'Please request a fresh OTP before placing the order.',
            ], 422);
        }

        if (Carbon::parse($generatedAt)->addMinutes(self::OTP_TTL_MINUTES)->isPast()) {
            $this->clearOtpSession();

            return response()->json([
                'status' => false,
                'message' => 'OTP expired. Please request a new OTP and try again.',
            ], 422);
        }

        if (! hash_equals($expectedOtp, $data['otp'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP. Please check the code and try again.',
            ], 422);
        }

        session([
            self::OTP_SESSION_KEY => null,
            self::OTP_GENERATED_AT_SESSION_KEY => null,
            self::OTP_VERIFIED_AT_SESSION_KEY => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    private function clearOtpSession(): void
    {
        session()->forget([
            self::OTP_SESSION_KEY,
            self::OTP_GENERATED_AT_SESSION_KEY,
            self::OTP_VERIFIED_AT_SESSION_KEY,
        ]);
    }
}
