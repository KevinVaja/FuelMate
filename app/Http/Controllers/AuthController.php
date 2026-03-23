<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegisterAccountRequest;
use App\Models\User;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    public function showLogin() {
        if (Auth::check()) return $this->redirectByRole();
        return view('auth.login');
    }
    public function showLoginAgent() {
        if (Auth::check()) return $this->redirectByRole();
        return view('auth.agent_login');
    }
    public function showLoginAdmin() {
        if (Auth::check()) return $this->redirectByRole();
        return view('auth.admin_login');
    }

    public function login(Request $request) {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials)) {
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
        return $this->redirectByRole();
    }

    public function showRegister() {
        return view('auth.register');
    }

    public function register(RegisterAccountRequest $request) {
        $data = $request->validated();
        $role = $data['role'] ?? 'user';
        $storedDocuments = [];
        $user = null;

        try {
            DB::transaction(function () use ($data, $role, $request, &$storedDocuments, &$user) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
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

        Auth::login($user);

        if ($user->role === 'agent') {
            return redirect()
                ->route('agent.dashboard')
                ->with('success', 'Registration submitted. Your petrol pump account is under verification.');
        }

        return $this->redirectByRole();
    }

    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    private function redirectByRole() {
        return match (Auth::user()->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'agent' => redirect()->route('agent.dashboard'),
            default => redirect()->route('user.dashboard'),
        };
    }

    private function expectedRoleForLogin(Request $request): ?string {
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

    private function storeAgentDocuments(RegisterAccountRequest $request): array {
        return [
            'petrol_license_photo' => $request->file('petrol_license_photo')->store('agent-documents', 'public'),
            'gst_certificate_photo' => $request->file('gst_certificate_photo')->store('agent-documents', 'public'),
            'owner_id_proof_photo' => $request->file('owner_id_proof_photo')->store('agent-documents', 'public'),
        ];
    }
}
