<?php

namespace App\Filament\Resources\Deposits\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('source')
                    ->required()
                    ->default('bank'),
                TextInput::make('provider'),
                TextInput::make('provider_ref'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
