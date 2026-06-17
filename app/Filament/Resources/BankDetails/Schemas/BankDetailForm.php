<?php

namespace App\Filament\Resources\BankDetails\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BankDetailForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('branch'),
                TextInput::make('account_holder_name'),
                TextInput::make('account_number'),
                TextInput::make('ifsc_code'),
                TextInput::make('upi_id'),
                TextInput::make('document'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
            ]);
    }
}
