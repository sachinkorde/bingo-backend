<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettingForm
{
    /** Internal keys that should not be edited manually by admin. */
    private const INTERNAL_KEYS = [
        'j_pending_until_slot',
        'j_hour_key',
        'j_hour_count',
    ];

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

                // ── OTP digits — special dropdown ────────────────────────────
                Select::make('value')
                    ->label('Value')
                    ->options([
                        '4' => '4 Digits',
                        '6' => '6 Digits',
                    ])
                    ->required()
                    ->visible(fn ($record) => $record?->key === 'otp_digits'),

                // ── J-Pool percentage ─────────────────────────────────────────
                TextInput::make('value')
                    ->label('Value (%)')
                    ->numeric()
                    ->minValue(5)
                    ->maxValue(20)
                    ->suffix('%')
                    ->required()
                    ->helperText('Percentage of net round profit transferred to J-Pool (5–20).')
                    ->visible(fn ($record) => $record?->key === 'j_pool_pct'),

                // ── Max J wins per hour ───────────────────────────────────────
                TextInput::make('value')
                    ->label('Max J wins / hour')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->required()
                    ->helperText('Maximum number of times J can win in one clock hour (1–10).')
                    ->visible(fn ($record) => $record?->key === 'j_max_per_hour'),

                // ── J delay (min rounds) ──────────────────────────────────────
                TextInput::make('value')
                    ->label('Min delay (rounds)')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Minimum rounds to wait before declaring J after conditions are met.')
                    ->visible(fn ($record) => $record?->key === 'j_delay_min_rounds'),

                // ── J delay (max rounds) ──────────────────────────────────────
                TextInput::make('value')
                    ->label('Max delay (rounds)')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Maximum rounds to wait before declaring J after conditions are met.')
                    ->visible(fn ($record) => $record?->key === 'j_delay_max_rounds'),

                // ── Internal / read-only settings ─────────────────────────────
                TextInput::make('value')
                    ->label('Value (read-only)')
                    ->disabled()
                    ->helperText('This is an internal tracking value managed by the game engine.')
                    ->visible(fn ($record) => in_array($record?->key, self::INTERNAL_KEYS, true)),

                // ── All other settings — plain text input ─────────────────────
                TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->visible(fn ($record) => ! in_array($record?->key, [
                        'otp_digits',
                        'j_pool_pct',
                        'j_max_per_hour',
                        'j_delay_min_rounds',
                        'j_delay_max_rounds',
                        ...self::INTERNAL_KEYS,
                    ], true)),
            ]);
    }
}
