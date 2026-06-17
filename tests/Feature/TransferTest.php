<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $mobile, float $fund = 0): string
    {
        $otp = app(OtpService::class)->generate($mobile, 'register');
        $token = $this->postJson('/api/register', [
            'mobile' => $mobile,
            'email' => $mobile . '@example.com',
            'password' => 'Bingo@123',
            'confirm_password' => 'Bingo@123',
            'otp' => $otp,
        ])->json('data.token');

        if ($fund > 0) {
            $this->withToken($token)->postJson('/api/add-amount', ['amount' => $fund])->assertOk();
        }

        return $token;
    }

    public function test_transfer_moves_money_between_users(): void
    {
        $senderToken = $this->register('9876530000', 1000);
        $this->register('9876530001'); // recipient

        $res = $this->withToken($senderToken)->postJson('/api/transfer', [
            'recipient' => '9876530001',
            'amount' => 300,
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('700.00', $res->json('data.balance'));

        $recipient = User::where('mobile', '9876530001')->first();
        $this->assertEquals('300.00', $recipient->wallet->balance);

        $this->assertDatabaseHas('transfers', [
            'recipient_id' => $recipient->id,
            'amount' => '300.00',
            'status' => 'completed',
        ]);
    }

    public function test_cannot_transfer_to_self(): void
    {
        $token = $this->register('9876530002', 500);

        $this->withToken($token)->postJson('/api/transfer', [
            'recipient' => '9876530002',
            'amount' => 100,
        ])->assertStatus(422);
    }

    public function test_transfer_requires_sufficient_funds(): void
    {
        $token = $this->register('9876530003', 0);
        $this->register('9876530004');

        $this->withToken($token)->postJson('/api/transfer', [
            'recipient' => '9876530004',
            'amount' => 100,
        ])->assertStatus(422);
    }

    public function test_transfer_to_unknown_recipient_fails(): void
    {
        $token = $this->register('9876530005', 500);

        $this->withToken($token)->postJson('/api/transfer', [
            'recipient' => '9111111111',
            'amount' => 100,
        ])->assertStatus(404);
    }
}
