<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Round;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_win_loss_stats_compute_correctly(): void
    {
        $user = User::create([
            'mobile' => '9000000001',
            'password' => 'secret123',
            'role' => 'user',
            'status' => 'active',
        ]);

        // Round 1: bet 100 on the winning number -> won 900.
        $r1 = Round::create(['slot_no' => 1, 'status' => 'settled', 'winning_number' => 3, 'betting_closes_at' => now()]);
        Bet::create(['round_id' => $r1->id, 'user_id' => $user->id, 'number' => 3, 'amount' => 100, 'payout' => 900, 'is_winner' => true]);

        // Round 2: bet 50 and lost.
        $r2 = Round::create(['slot_no' => 2, 'status' => 'settled', 'winning_number' => 7, 'betting_closes_at' => now()]);
        Bet::create(['round_id' => $r2->id, 'user_id' => $user->id, 'number' => 1, 'amount' => 50, 'payout' => 0, 'is_winner' => false]);

        $this->assertSame(2, $user->roundsPlayed());
        $this->assertSame(1, $user->roundsWon());
        $this->assertSame(50.0, $user->winRate());
        $this->assertSame('750.00', $user->netProfit()); // won 900 - wagered 150
    }
}
