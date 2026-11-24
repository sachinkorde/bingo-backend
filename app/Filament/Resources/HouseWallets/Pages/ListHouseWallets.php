<?php

namespace App\Filament\Resources\HouseWallets\Pages;

use App\Filament\Resources\HouseWallets\HouseWalletResource;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHouseWallets extends ListRecords
{
    protected static string $resource = HouseWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forceJNextRound')
                ->label('⚡ Force J Next Round')
                ->color('warning')
                ->icon(Heroicon::OutlinedBolt)
                ->requiresConfirmation()
                ->modalHeading('Force J on Next Round')
                ->modalDescription('The very next round will guarantee J as the winner, bypassing bet limits, pool balance, and delay. Insufficient pool balance will be auto-covered for testing.')
                ->action(function () {
                    Setting::put('j_force_next_round', '1');
                    Notification::make()
                        ->title('⚡ J is forced for the next round!')
                        ->success()
                        ->send();
                }),

            Action::make('toggleJ')
                ->label(fn () => Setting::get('j_enabled', '1') === '1' ? '🟢 J Winning: Active' : '🔴 J Winning: Disabled')
                ->color(fn () => Setting::get('j_enabled', '1') === '1' ? 'success' : 'danger')
                ->requiresConfirmation()
                ->modalHeading('Toggle J Winning')
                ->modalDescription('When disabled, J will never win, even if bets are placed.')
                ->action(function () {
                    $current = Setting::get('j_enabled', '1');
                    $new = $current === '1' ? '0' : '1';
                    Setting::put('j_enabled', $new);
                    Notification::make()
                        ->title($new === '1' ? 'J Winning Enabled' : 'J Winning Disabled')
                        ->success()
                        ->send();
                }),
        ];
    }
}
