<?php

namespace App\Filament\Resources\Transfers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('sender.mobile')
                    ->label('From')
                    ->searchable(),
                TextColumn::make('recipient.mobile')
                    ->label('To')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'completed' ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            // Player-initiated, read-only in the dashboard.
            ->recordActions([])
            ->toolbarActions([]);
    }
}
