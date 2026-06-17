<?php

namespace App\Filament\Resources\Settings\Schemas;

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
                TextInput::make('value')
                    ->label('Value')
                    ->required(),
            ]);
    }
}
