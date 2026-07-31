<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\WalletTransaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only: who this player referred, and whether the inviter has actually
 * been PAID for each one yet (their first successful deposit). Referrals are
 * created implicitly at registration (referred_by = the inviter's code) —
 * there is nothing here for an admin to create/edit/delete.
 */
class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static ?string $title = 'Referrals';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('mobile')
            ->columns([
                TextColumn::make('name')
                    ->default('Player')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('bonus_status')
                    ->label('Bonus')
                    ->badge()
                    ->state(function ($record): string {
                        $paid = WalletTransaction::where('user_id', $this->getOwnerRecord()->id)
                            ->where('type', 'referral_bonus')
                            ->where('reference', "referral:{$record->id}")
                            ->exists();

                        return $paid ? 'Earned' : 'Pending';
                    })
                    ->color(fn (string $state): string => $state === 'Earned' ? 'success' : 'warning'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
