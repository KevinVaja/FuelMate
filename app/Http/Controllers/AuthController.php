<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterAccountRequest;
use App\Models\Agent;
use App\Models\User;
use App\Services\EmailOtpService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const REGISTRATION_OTP_TTL_MINUTES = 10;

    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('auth.login');
    }

    public function showLoginAgent()
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('auth.agent_login');
    }

    public function showLoginAdmin()
    {
        if (Auth::check()) {
            return $this->redirectByRole();
        }

        return view('auth.admin_login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }

        if (Auth::user()->status === 'blocked') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended. Please contact support.',
            ]);
        }

        $expectedRole = $this->expectedRoleForLogin($request);
        if ($expectedRole && Auth::user()->role !== $expectedRole) {
            $message = match ($expectedRole) {
                'agent' => 'This login is only for petrol pump agents. Please use the customer login.',
                'user' => 'This login is only for customers. Please use the agent login.',
                'admin' => 'This login is only for administrators.',
                default => 'This login is not available for your account role.',
            };

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => $message]);
        }

        $request->session()->regenerate();
        $authenticatedUser = Auth::user();

        if ($authenticatedUser?->role === 'agent') {
            $authenticatedUser->loadMissing('agent');
            $authenticatedUser->agent?->syncPresence();
        }

        return $this->redirectByRole();
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function sendRegistrationOtp(Request $request, EmailOtpService $emailOtpService)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);

        try {
            $emailOtpService->sendCode(
                $request,
                EmailOtpService::PURPOSE_REGISTRATION,
                $data['email'],
                'registration',
                self::REGISTRATION_OTP_TTL_MINUTES,
            );
        } catch (DomainException $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'status' => true,
            'message' => 'We sent a 6-digit verification code to ' . $emailOtpService->maskEmail($data['email']) . '.',
            'expires_in_seconds' => self::REGISTRATION_OTP_TTL_MINUTES * 60,
        ]);
    }

    public function verifyRegistrationOtp(Request $request, EmailOtpService $emailOtpService)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => ['required', 'digits:6'],
        ]);

        $result = $emailOtpService->verifyCode(
            $request,
            EmailOtpService::PURPOSE_REGISTRATION,
            $data['email'],
            $data['otp'],
        );

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    public function register(RegisterAccountRequest $request, EmailOtpService $emailOtpService)
    {
        $data = $request->validated();
        $role = $data['role'] ?? 'user';
        $storedDocuments = [];
        $user = null;

        if (! $emailOtpService->hasFreshVerification($request, EmailOtpService::PURPOSE_REGISTRATION, $data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Verify this email address before creating the account.',
            ]);
        }

        try {
            DB::transaction(function () use ($data, $role, $request, &$storedDocuments, &$user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'email_verified_at' => now(),
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make($data['password']),
                    'role' => $role,
                    'status' => 'active',
                ]);

                if ($role !== 'agent') {
                    return;
                }

                $storedDocuments = $this->storeAgentDocuments($request);

                Agent::create([
                    'user_id' => $user->id,
                    'vehicle_type' => 'Petrol Pump Business',
                    'vehicle_license_plate' => 'Pending Assignment',
                    'approval_status' => 'pending',
                    'petrol_license_photo' => $storedDocuments['petrol_license_photo'],
                    'gst_certificate_photo' => $storedDocuments['gst_certificate_photo'],
                    'owner_id_proof_photo' => $storedDocuments['owner_id_proof_photo'],
                    'verification_status' => Agent::VERIFICATION_PENDING,
                    'is_available' => false,
                    'status' => Agent::STATUS_OFFLINE,
                    'last_active_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            foreach ($storedDocuments as $path) {
                if (is_string($path) && $path !== '') {
                    Storage::disk('public')->delete($path);
                }
            }

            throw $exception;
        }

        $emailOtpService->clearState($request, EmailOtpService::PURPOSE_REGISTRATION);
        Auth::login($user);

        if ($user->role === 'agent') {
            return redirect()
                ->route('agent.dashboard')
                ->with('success', 'Registration submitted. Your petrol pump account is under verification.');
        }

        return $this->redirectByRole();
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user?->role === 'agent') {
            $user->loadMissing('agent');
            $user->agent?->markOffline();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectByRole()
    {
        return match (Auth::user()->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'agent' => redirect()->route('agent.dashboard'),
            default => redirect()->route('user.dashboard'),
        };
    }

    private function expectedRoleForLogin(Request $request): ?string
    {
        if ($request->is('agent-login')) {
            return 'agent';
        }

        if ($request->is('admin-login')) {
            return 'admin';
        }

        if ($request->is('login')) {
            return 'user';
        }

        return null;
    }

    private function storeAgentDocuments(RegisterAccountRequest $request): array
    {
        return [
            'petrol_license_photo' => $request->file('petrol_license_photo')->store('agent-documents', 'public'),
            'gst_certificate_photo' => $request->file('gst_certificate_photo')->store('agent-documents', 'public'),
            'owner_id_proof_photo' => $request->file('owner_id_proof_photo')->store('agent-documents', 'public'),
        ];
    }
}
