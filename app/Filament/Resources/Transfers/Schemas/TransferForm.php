<?php

namespace App\Filament\Resources\Transfers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sender_id')
                    ->relationship('sender', 'name')
                    ->required(),
                Select::make('recipient_id')
                    ->relationship('recipient', 'name')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('completed'),
                TextInput::make('note'),
            ]);
    }
}
