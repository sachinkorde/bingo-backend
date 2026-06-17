<?php

namespace App\Filament\Resources\Rounds\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('slot_no', 'desc')
            ->columns([
                TextColumn::make('slot_no')
                    ->label('Session')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'settled' ? 'success' : 'warning'),
                TextColumn::make('winning_number')
                    ->label('Winning #')
                    ->numeric()
                    ->placeholder('—'),
                TextColumn::make('total_bet')
                    ->label('Total bet')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('total_payout')
                    ->label('Total paid')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('earning')
                    ->label('House earning')
                    ->state(fn ($record) => $record->earning())
                    ->money('INR')
                    ->color(fn ($record) => bccomp((string) $record->earning(), '0', 2) >= 0 ? 'success' : 'danger'),
                TextColumn::make('settled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('server_seed_hash')
                    ->label('Seed hash')
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            // Server-generated, read-only.
            ->recordActions([])
            ->toolbarActions([]);
    }
}
