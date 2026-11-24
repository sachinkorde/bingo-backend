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

                // ── J enabled toggle ─────────────────────────────────────────
                Select::make('value')
                    ->label('J Winning Status')
                    ->options([
                        '1' => 'Enabled (J can be considered in winning)',
                        '0' => 'Disabled (J will NEVER win)',
                    ])
                    ->required()
                    ->helperText('Enable or disable J from winning.')
                    ->visible(fn ($record) => $record?->key === 'j_enabled'),

                // ── J test mode ───────────────────────────────────────────────
                Select::make('value')
                    ->label('J QA Test Mode')
                    ->options([
                        '0' => 'Disabled (Production rules: strict pool, bet limits & delay)',
                        '1' => 'Active (QA Test Mode: bypass matching bet & delay requirements)',
                    ])
                    ->required()
                    ->helperText('Bypasses strict conditions for easy testing.')
                    ->visible(fn ($record) => $record?->key === 'j_test_mode'),

                // ── J force next round ────────────────────────────────────────
                Select::make('value')
                    ->label('Force J on Next Round')
                    ->options([
                        '0' => 'Off (Normal rotation)',
                        '1' => '⚡ Forced on Next Round (Guaranteed J win next round)',
                    ])
                    ->required()
                    ->helperText('Forces J to win on the upcoming round, then resets automatically.')
                    ->visible(fn ($record) => $record?->key === 'j_force_next_round'),

                // ── Max bet per number ────────────────────────────────────────
                TextInput::make('value')
                    ->label('Max Bet per Number (₹)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->required()
                    ->helperText('Maximum total bet allowed per number in ₹ (enter 0 for unlimited).')
                    ->visible(fn ($record) => $record?->key === 'max_bet_per_number'),

                // ── Transfer max per day ──────────────────────────────────────
                TextInput::make('value')
                    ->label('Daily Transfer Limit (₹)')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('₹')
                    ->required()
                    ->helperText('Maximum total amount a player can transfer per day in ₹.')
                    ->visible(fn ($record) => $record?->key === 'transfer_max_per_day'),

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
                        'j_enabled',
                        'j_test_mode',
                        'j_force_next_round',
                        'max_bet_per_number',
                        'transfer_max_per_day',
                        'j_pool_pct',
                        'j_max_per_hour',
                        'j_delay_min_rounds',
                        'j_delay_max_rounds',
                        ...self::INTERNAL_KEYS,
                    ], true)),
            ]);
    }
}
