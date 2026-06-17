<?php

namespace App\Filament\Resources\Rounds\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slot_no')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('betting'),
                DateTimePicker::make('betting_started_at'),
                DateTimePicker::make('betting_closes_at'),
                DateTimePicker::make('settled_at'),
                TextInput::make('winning_number')
                    ->numeric(),
                TextInput::make('server_seed'),
                TextInput::make('server_seed_hash'),
                TextInput::make('total_bet')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_payout')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
