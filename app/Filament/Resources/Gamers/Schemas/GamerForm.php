<?php

namespace App\Filament\Resources\Gamers\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GamerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('name'),
                TextInput::make('gender'),
                TextInput::make('date_of_birth'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('adhar_number'),
                TextInput::make('pan_number'),
                TextInput::make('adhar_document'),
                TextInput::make('pan_document'),
                FileUpload::make('profile_image')
                    ->image(),
                // A Select, not free text: withdrawals check for exactly
                // 'verified', so a typo here locks the player out silently.
                Select::make('kyc_status')
                    ->options([
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),
            ]);
    }
}
