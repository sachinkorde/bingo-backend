<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep betting rounds flowing on the server clock. For sub-minute rounds in
// production, run this from a worker/loop; everyMinute is the safety heartbeat.
Schedule::command('game:rotate-rounds')->everyMinute()->withoutOverlapping();
