<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate, NOT updateOrCreate: this seeder runs on every deploy,
        // and must never clobber a value the admin has changed in the dashboard.
        Setting::firstOrCreate(
            ['key' => 'referral_bonus'],
            [
                'value' => (string) config('game.referral_bonus', 50),
                'label' => 'Referral bonus (₹ paid to the inviter on their invitee\'s first deposit)',
            ]
        );

        Setting::firstOrCreate(
            ['key' => 'otp_digits'],
            [
                'value' => (string) config('game.otp_digits', 6),
                'label' => 'OTP length in digits (4 or 6)',
            ]
        );
    }
}
