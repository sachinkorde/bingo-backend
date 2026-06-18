<?php

namespace Database\Seeders;

use App\Models\Deposit;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Database\Seeder;

/**
 * A few clean demo players (with a deposit each) so the dashboard isn't empty
 * when showing the client. Real bets/sessions appear once people actually play.
 */
class DemoPlayersSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = app(WalletService::class);

        $players = [
            ['name' => 'Rahul Sharma', 'mobile' => '9000000001', 'email' => 'rahul@example.com', 'deposit' => 5000],
            ['name' => 'Priya Patel',  'mobile' => '9000000002', 'email' => 'priya@example.com', 'deposit' => 2000],
            ['name' => 'Amit Kumar',   'mobile' => '9000000003', 'email' => 'amit@example.com',  'deposit' => 10000],
        ];

        foreach ($players as $p) {
            $user = User::updateOrCreate(
                ['mobile' => $p['mobile']],
                [
                    'name' => $p['name'],
                    'email' => $p['email'],
                    'password' => 'Player@123',
                    'role' => 'user',
                    'status' => 'active',
                    'referral_code' => 'TRB' . random_int(10000, 99999),
                ]
            );

            $user->gamer()->firstOrCreate(['user_id' => $user->id], [
                'name' => $p['name'],
                'kyc_status' => 'pending',
            ]);

            $wallets->getOrCreate($user);

            $deposit = Deposit::create([
                'user_id' => $user->id,
                'amount' => $p['deposit'],
                'source' => 'bank',
                'status' => 'success',
            ]);
            $wallets->credit($user, $p['deposit'], 'deposit', "deposit:{$deposit->id}");
        }
    }
}
