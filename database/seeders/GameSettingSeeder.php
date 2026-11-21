<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the J-game settings rows. Safe to re-run (uses updateOrCreate via Setting::put).
 * Admin-configurable keys are created if missing; internal tracking keys default to empty/zero.
 */
class GameSettingSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin-configurable ────────────────────────────────────────────────
        Setting::put('j_pool_pct',          '10',  'J-Pool % of net profit (5–20)');
        Setting::put('j_max_per_hour',       '3',   'Max J wins per hour (1–10)');
        Setting::put('j_delay_min_rounds',   '2',   'Min rounds before J is declared (anti-prediction)');
        Setting::put('j_delay_max_rounds',   '5',   'Max rounds before J is declared (anti-prediction)');

        // ── Internal state (not for manual editing) ───────────────────────────
        Setting::put('j_pending_until_slot', '0',   '[internal] Slot no. at which pending J is declared');
        Setting::put('j_hour_key',           '',    '[internal] Current hour key for J count reset');
        Setting::put('j_hour_count',         '0',   '[internal] J wins in the current hour');
    }
}
