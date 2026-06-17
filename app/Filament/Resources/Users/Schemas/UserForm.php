<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                TextInput::make('mobile')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('role')
                    ->required()
                    ->default('user'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                TextInput::make('referral_code'),
                TextInput::make('referred_by'),
            ]);
    }
}
