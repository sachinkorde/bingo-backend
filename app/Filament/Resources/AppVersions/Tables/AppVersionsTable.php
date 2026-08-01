<?php

namespace App\Filament\Resources\AppVersions\Tables;

use App\Models\AppVersion;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AppVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('version_code', 'desc')
            ->columns([
                TextColumn::make('version_code')
                    ->label('Code')
                    ->sortable(),

                TextColumn::make('version_name')
                    ->label('Version')
                    ->searchable(),

                TextColumn::make('download')
                    ->label('Download')
                    ->state(fn (AppVersion $record): string => $record->resolvedDownloadUrl() ? 'Available' : 'No file/link')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Available' ? 'success' : 'danger')
                    ->url(fn (AppVersion $record): ?string => $record->resolvedDownloadUrl(), shouldOpenInNewTab: true),

                IconColumn::make('is_mandatory')
                    ->label('Forced')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Published')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Released')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
