<?php

namespace App\Console\Commands;

use App\Services\RoundService;
use Illuminate\Console\Command;

/**
 * Heartbeat that settles the expired round and opens the next one, so rounds
 * keep flowing even when no player is requesting them. Scheduled in
 * routes/console.php. (Local dev also rotates lazily on each request.)
 */
class RotateRounds extends Command
{
    protected $signature = 'game:rotate-rounds';

    protected $description = 'Settle the expired betting round (if any) and open the next one.';

    public function handle(RoundService $rounds): int
    {
        $round = $rounds->currentRound();

        $this->info("Active round #{$round->slot_no} (id {$round->id}) — {$round->status}, closes {$round->betting_closes_at}.");

        return self::SUCCESS;
    }
}
