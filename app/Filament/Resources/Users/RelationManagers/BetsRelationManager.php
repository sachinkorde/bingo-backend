<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BetsRelationManager extends RelationManager
{
    protected static string $relationship = 'bets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('round_id')
                    ->relationship('round', 'id')
                    ->required(),
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('payout')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_winner')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('round.slot_no')
                    ->label('Round')
                    ->sortable(),
                TextColumn::make('number')
                    ->label('Bet on')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('round.winning_number')
                    ->label('Winning #')
                    ->placeholder('— pending'),
                IconColumn::make('is_winner')
                    ->label('Won?')
                    ->boolean(),
                TextColumn::make('payout')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Played')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            // Bets are a record of play — read-only in the dashboard.
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
