<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'referral_bonus'],
            [
                'value' => (string) config('game.referral_bonus', 50),
                'label' => 'Referral bonus (₹ paid to the inviter on their invitee\'s first deposit)',
            ]
        );
    }
}
