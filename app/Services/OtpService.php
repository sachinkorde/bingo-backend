<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Generates and verifies one-time passwords.
 *
 * The OTP is stored HASHED and is NEVER returned in an API response. In local
 * dev it is written to the Laravel log so you can read it while testing; in
 * production this is where you'd hand the code to an SMS provider.
 */
class OtpService
{
    public function generate(string $mobile, string $module): string
    {
        $digits = (int) Setting::get('otp_digits', config('game.otp_digits', 6));
        $digits = in_array($digits, [4, 6], true) ? $digits : 6;

        $min = $digits === 4 ? 1000 : 100000;
        $max = $digits === 4 ? 9999 : 999999;

        $otp = (string) random_int($min, $max);

        Otp::create([
            'mobile' => $mobile,
            'otp_hash' => Hash::make($otp),
            'module' => $module,
            'expires_at' => Carbon::now()->addSeconds((int) config('game.otp_ttl_seconds', 300)),
            'attempts' => 0,
        ]);

        // Local/dev visibility only — do NOT expose this to the client.
        Log::info("OTP [{$module}] for {$mobile}: {$otp}");

        // TODO: dispatch via licensed SMS provider in production.
        return $otp;
    }

    public function verify(string $mobile, string $module, string $otp): bool
    {
        $record = Otp::where('mobile', $mobile)
            ->where('module', $module)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $record || $record->isExpired()) {
            return false;
        }

        if ($record->attempts >= (int) config('game.otp_max_attempts', 5)) {
            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($otp, $record->otp_hash)) {
            return false;
        }

        $record->consumed_at = now();
        $record->save();

        return true;
    }
}
