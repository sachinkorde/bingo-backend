<?php

namespace App\Filament\Resources\Gamers\Tables;

use App\Models\Gamer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GamersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('gender')
                    ->searchable(),
                TextColumn::make('date_of_birth')
                    ->searchable(),
                TextColumn::make('adhar_number')
                    ->searchable(),
                TextColumn::make('pan_number')
                    ->searchable(),
                TextColumn::make('adhar_document')
                    ->searchable(),
                TextColumn::make('pan_document')
                    ->searchable(),
                ImageColumn::make('profile_image'),
                TextColumn::make('kyc_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // One click, exact value. Typing the status by hand risked
                // "Verified"/"VERIFIED", which fails the strict === 'verified'
                // check in WalletController::withdraw and silently keeps the
                // player locked out of withdrawals.
                Action::make('verifyKyc')
                    ->label('Verify KYC')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirm the Aadhaar/PAN documents match this player before verifying. Verified players can request withdrawals.')
                    ->visible(fn (Gamer $record): bool => $record->kyc_status !== 'verified')
                    ->action(fn (Gamer $record) => $record->update(['kyc_status' => 'verified'])),
                Action::make('rejectKyc')
                    ->label('Reject KYC')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Gamer $record): bool => $record->kyc_status !== 'rejected')
                    ->action(fn (Gamer $record) => $record->update(['kyc_status' => 'rejected'])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
