<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: an account created directly (e.g. from the admin
     * panel, which has no gamer-creation hook the way registration does) has
     * no `gamers` row. GET /profile must not 500, and must still return a
     * usable `gamer` object rather than null — the Unity client assumes it is
     * always present and previously crashed its whole UI update on this.
     */
    public function test_profile_show_creates_a_gamer_row_when_missing(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876560001',
            'referral_code' => 'TRB99999',
        ]);
        $this->assertDatabaseMissing('gamers', ['user_id' => $user->id]);

        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/profile')->assertOk();

        $this->assertNotNull($res->json('data.gamer'));
        $this->assertSame('pending', $res->json('data.gamer.kyc_status'));
        $this->assertDatabaseHas('gamers', ['user_id' => $user->id]);
    }

    public function test_profile_show_does_not_duplicate_an_existing_gamer_row(): void
    {
        $user = User::factory()->create(['mobile' => '9876560002', 'referral_code' => 'TRB88888']);
        $user->gamer()->create(['name' => 'Existing Name', 'kyc_status' => 'verified']);

        $token = $user->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->getJson('/api/profile')->assertOk();

        $this->assertSame('Existing Name', $res->json('data.gamer.name'));
        $this->assertSame('verified', $res->json('data.gamer.kyc_status'));
        $this->assertSame(1, $user->fresh()->gamer()->count());
    }
}
