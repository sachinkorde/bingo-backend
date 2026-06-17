<?php

namespace App\Filament\Resources\Withdrawals\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class WithdrawalForm
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
                TextInput::make('method')
                    ->required()
                    ->default('bank'),
                Select::make('bank_detail_id')
                    ->relationship('bankDetail', 'name'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('processed_by')
                    ->numeric(),
                TextInput::make('provider_ref'),
                Textarea::make('remark')
                    ->columnSpanFull(),
            ]);
    }
}
