<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Send OTP for a module (register | forgot_password).
     * The OTP is NOT returned — it is logged (dev) / SMS'd (prod).
     */
    public function sendOtp(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'module' => ['required', 'in:register,forgot_password'],
        ]);

        $exists = User::where('mobile', $data['mobile'])->exists();

        if ($data['module'] === 'forgot_password' && ! $exists) {
            return $this->fail('Mobile number not registered or invalid!', 422);
        }
        if ($data['module'] === 'register' && $exists) {
            return $this->fail('Mobile number already registered.', 422);
        }

        $code = $otp->generate($data['mobile'], $data['module']);

        // DEV CONVENIENCE: return the OTP in non-production so testers don't have
        // to read the log. In PRODUCTION this is null — the OTP is never exposed,
        // UNLESS OTP_DEBUG_EXPOSE=true is set to allow testing on a deployed
        // server that has no SMS provider yet. See config/game.php for the
        // warning that goes with that flag.
        $expose = ! app()->environment('production') || config('game.otp_debug_expose');

        $payload = $expose ? (int) $code : null;

        return $this->ok($payload, 'OTP sent successfully!');
    }

    /**
     * Register a new player (requires a valid 'register' OTP).
     */
    public function register(Request $request, OtpService $otp, WalletService $wallets)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:users,mobile'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'same:password'],
            'otp' => ['required', 'string'],
            'referral' => ['nullable', 'string'],
        ], [
            'mobile.required' => 'Please enter your mobile number.',
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'This mobile number is already registered.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.min' => 'Password must be at least 6 characters.',
            'confirm_password.same' => 'Passwords do not match.',
            'otp.required' => 'Please enter the OTP.',
        ]);

        if (! $otp->verify($data['mobile'], 'register', $data['otp'])) {
            return $this->fail('Invalid or expired OTP.', 422);
        }

        $referredBy = null;
        if (! empty($data['referral'])) {
            $referredBy = User::where('referral_code', $data['referral'])->value('referral_code');
        }

        $user = DB::transaction(function () use ($data, $referredBy, $wallets) {
            $user = User::create([
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'], // 'hashed' cast handles bcrypt
                'referral_code' => $this->generateReferralCode(),
                'referred_by' => $referredBy,
            ]);

            $user->gamer()->create(['kyc_status' => 'pending']);
            $wallets->getOrCreate($user);

            return $user;
        });

        $token = $user->createToken('app')->plainTextToken;

        return $this->ok(['token' => $token], 'Registration successful!');
    }

    /**
     * Login with mobile or email + password.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('mobile', $data['credential'])
            ->orWhere('email', $data['credential'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('Invalid credentials.', 401);
        }

        if ($user->status !== 'active') {
            return $this->fail('Account is disabled.', 403);
        }

        $token = $user->createToken('app')->plainTextToken;

        return $this->ok(['token' => $token], 'Login successful!');
    }

    /**
     * Forgot password — verify OTP, return a short-lived token the client
     * uses to authorize the reset-password call.
     */
    public function forgotPassword(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        if (! $otp->verify($data['mobile'], 'forgot_password', $data['otp'])) {
            return $this->fail('Invalid or expired OTP.', 422);
        }

        $user = User::where('mobile', $data['mobile'])->first();
        if (! $user) {
            return $this->fail('Mobile number not registered.', 422);
        }

        $token = $user->createToken('password-reset', ['reset-password'])->plainTextToken;

        return $this->ok(['token' => $token], 'OTP verified!');
    }

    /**
     * Reset password (authorized by the token from forgotPassword, or a
     * logged-in session). Revokes the token afterwards.
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'same:password'],
        ]);

        $user = $request->user();
        $user->password = $data['password'];
        $user->save();

        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Password reset successful!');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out successfully!');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->ok(null, 'Logged out from all devices!');
    }

    private function generateReferralCode(): string
    {
        do {
            $code = 'TRB' . random_int(10000, 99999);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    public function checkUsername(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();
        if ($user) {
            return $this->fail('Username already exists.', 422);
        }

        return $this->ok(null, 'Username available!');
    }
}
