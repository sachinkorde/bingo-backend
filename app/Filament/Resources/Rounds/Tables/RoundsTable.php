<?php

namespace App\Filament\Resources\Rounds\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slot_no')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('betting_started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('betting_closes_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('settled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('winning_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('server_seed')
                    ->searchable(),
                TextColumn::make('server_seed_hash')
                    ->searchable(),
                TextColumn::make('total_bet')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_payout')
                    ->numeric()
                    ->sortable(),
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
