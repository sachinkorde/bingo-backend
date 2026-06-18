<?php

namespace App\Filament\Widgets;

use App\Models\Bet;
use App\Models\Deposit;
use App\Models\Round;
use App\Models\User;
use App\Models\Withdrawal;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class DashboardStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Overview';

    // Financial KPIs are for superadmin/admin only.
    public static function canView(): bool
    {
        return \App\Support\AdminAccess::canManageAdmins();
    }

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        $players = User::where('role', 'user')->count();
        $playersToday = User::where('role', 'user')->where('created_at', '>=', $today)->count();

        $deposits = (float) Deposit::where('status', 'success')->sum('amount');
        $depositsToday = (float) Deposit::where('status', 'success')->where('created_at', '>=', $today)->sum('amount');

        $withdrawals = (float) Withdrawal::whereIn('status', ['approved', 'paid'])->sum('amount');
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();

        $betsToday = (float) Bet::where('created_at', '>=', $today)->sum('amount');

        // House earning = total bet - total payout across settled sessions.
        $totalBet = (float) Round::where('status', 'settled')->sum('total_bet');
        $totalPaid = (float) Round::where('status', 'settled')->sum('total_payout');
        $earningAll = $totalBet - $totalPaid;

        $earnToday = (float) Round::where('status', 'settled')->where('settled_at', '>=', $today)->sum('total_bet')
                   - (float) Round::where('status', 'settled')->where('settled_at', '>=', $today)->sum('total_payout');

        return [
            Stat::make('Players', number_format($players))
                ->description($playersToday . ' joined today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Deposits (total)', '₹' . number_format($deposits))
                ->description('₹' . number_format($depositsToday) . ' today')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Withdrawals (paid)', '₹' . number_format($withdrawals))
                ->description($pendingWithdrawals . ' pending approval')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color($pendingWithdrawals > 0 ? 'warning' : 'gray'),

            Stat::make('Bets today', '₹' . number_format($betsToday))
                ->description('Wagered since midnight')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('House earning (today)', '₹' . number_format($earnToday))
                ->description('₹' . number_format($earningAll) . ' all-time')
                ->descriptionIcon('heroicon-m-trophy')
                ->color($earnToday >= 0 ? 'success' : 'danger'),
        ];
    }
}
