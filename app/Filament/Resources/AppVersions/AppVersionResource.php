<?php

namespace App\Filament\Resources\AppVersions;

use App\Filament\Resources\AppVersions\Pages\CreateAppVersion;
use App\Filament\Resources\AppVersions\Pages\EditAppVersion;
use App\Filament\Resources\AppVersions\Pages\ListAppVersions;
use App\Filament\Resources\AppVersions\Schemas\AppVersionForm;
use App\Filament\Resources\AppVersions\Tables\AppVersionsTable;
use App\Models\AppVersion;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AppVersionResource extends Resource
{
    protected static ?string $model = AppVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'App Releases';

    protected static ?int $navigationSort = 3;

    // Publishing a build can lock every player out of the game if the
    // mandatory flag is set — superadmin/admin only, same gate as Settings.
    public static function canViewAny(): bool
    {
        return AdminAccess::canManageAdmins();
    }

    public static function form(Schema $schema): Schema
    {
        return AppVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppVersions::route('/'),
            'create' => CreateAppVersion::route('/create'),
            'edit' => EditAppVersion::route('/{record}/edit'),
        ];
    }
}
