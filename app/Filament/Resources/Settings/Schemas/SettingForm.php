<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Key + label are fixed; admins only change the value.
                TextInput::make('label')
                    ->label('Setting')
                    ->disabled(),
                TextInput::make('key')
                    ->disabled(),
                Select::make('value')
                    ->label('Value')
                    ->options([
                        '4' => '4 Digits',
                        '6' => '6 Digits',
                    ])
                    ->required()
                    ->visible(fn ($record) => $record?->key === 'otp_digits'),
                TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->visible(fn ($record) => $record?->key !== 'otp_digits'),
            ]);
    }
}
