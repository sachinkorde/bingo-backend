<?php

namespace App\Filament\Resources\HouseWallets;

use App\Filament\Resources\HouseWallets\Pages\ListHouseWallets;
use App\Models\HouseWallet;
use App\Services\HouseWalletService;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HouseWalletResource extends Resource
{
    protected static ?string $model = HouseWallet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?string $navigationLabel = 'House Wallets';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return AdminAccess::current()?->isSuperadmin() ?? false;
    }

    public static function canCreate(): bool  { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Wallet')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'j_pool'       => '🃏 J-Pool',
                        'admin_profit' => '💼 Admin Profit',
                        default        => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === 'j_pool' ? 'info' : 'success'),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('INR')
                    ->sortable()
                    ->color(fn ($record) => bccomp((string) $record->balance, '0', 2) >= 0 ? 'success' : 'danger'),

                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('topUp')
                    ->label('Top Up')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('info')
                    ->visible(fn (HouseWallet $record) => in_array($record->type, ['j_pool', 'admin_profit']))
                    ->form([
                        TextInput::make('amount')
                            ->label('Top-up amount (₹)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('note')
                            ->label('Note (optional)')
                            ->maxLength(255),
                    ])
                    ->action(function (HouseWallet $record, array $data) {
                        app(HouseWalletService::class)->credit(
                            $record->type,
                            (string) $data['amount'],
                            'manual',
                            'admin-topup',
                            ['note' => $data['note'] ?? null]
                        );

                        $walletName = $record->type === 'j_pool' ? 'J-Pool' : 'Admin Profit';
                        Notification::make()
                            ->title("{$walletName} topped up by ₹" . number_format((float) $data['amount'], 2))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHouseWallets::route('/'),
        ];
    }
}
