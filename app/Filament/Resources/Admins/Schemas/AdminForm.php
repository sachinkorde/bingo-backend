<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Support\AdminAccess;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    // Required when creating; on edit, only updates if a new value is typed.
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state): bool => filled($state)),
                Select::make('role')
                    ->options(fn (): array => (AdminAccess::current()?->isSuperadmin() ?? false)
                        ? ['admin' => 'Admin', 'subadmin' => 'Subadmin']
                        : ['subadmin' => 'Subadmin'])
                    ->default('subadmin')
                    ->required()
                    ->live(),
                CheckboxList::make('permissions')
                    ->label('Subadmin can view')
                    ->options(AdminAccess::resourceOptions())
                    ->columns(2)
                    ->visible(fn ($get): bool => $get('role') === 'subadmin'),
                Select::make('status')
                    ->options(['active' => 'Active', 'disabled' => 'Disabled'])
                    ->default('active')
                    ->required(),
            ]);
    }
}
