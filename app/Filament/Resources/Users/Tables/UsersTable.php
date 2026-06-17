<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn ($record) => $record->wallet?->balance ?? '0.00')
                    ->money('INR'),
                TextColumn::make('win_rate')
                    ->label('Win %')
                    ->state(fn ($record) => $record->winRate() . '%'),
                TextColumn::make('net_profit')
                    ->label('Net (player)')
                    ->state(fn ($record) => $record->netProfit())
                    ->money('INR')
                    ->color(fn ($record) => bccomp((string) $record->netProfit(), '0', 2) >= 0 ? 'success' : 'danger'),
                TextColumn::make('referral_code')
                    ->searchable(),
                TextColumn::make('referred_by')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
