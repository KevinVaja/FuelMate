<?php

namespace App\Http\Controllers;

use App\Services\EmailOtpService;
use DomainException;
use Illuminate\Http\Request;

class FuelRequestController extends Controller
{
    private const OTP_TTL_MINUTES = 5;

    public function sendOtp(Request $request, EmailOtpService $emailOtpService)
    {
        $user = $request->user();

        try {
            $emailOtpService->sendCode(
                $request,
                EmailOtpService::PURPOSE_ORDER,
                (string) $user->email,
                'order confirmation',
                self::OTP_TTL_MINUTES,
                (string) $user->name,
            );
        } catch (DomainException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'status' => true,
            'message' => 'We sent a 6-digit order confirmation code to your registered email ' . $emailOtpService->maskEmail((string) $user->email) . '.',
            'expires_in_seconds' => self::OTP_TTL_MINUTES * 60,
        ]);
    }

    public function verifyOtp(Request $request, EmailOtpService $emailOtpService)
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $result = $emailOtpService->verifyCode(
            $request,
            EmailOtpService::PURPOSE_ORDER,
            (string) $request->user()->email,
            $data['otp'],
        );

        return response()->json($result, $result['status'] ? 200 : 422);
    }
}
