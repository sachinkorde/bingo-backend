<?php

namespace App\Filament\Resources\Gamers;

use App\Filament\Resources\Gamers\Pages\CreateGamer;
use App\Filament\Resources\Gamers\Pages\EditGamer;
use App\Filament\Resources\Gamers\Pages\ListGamers;
use App\Filament\Resources\Gamers\Schemas\GamerForm;
use App\Filament\Resources\Gamers\Tables\GamersTable;
use App\Models\Gamer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GamerResource extends Resource
{
    protected static ?string $model = Gamer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return GamerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GamersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGamers::route('/'),
            'create' => CreateGamer::route('/create'),
            'edit' => EditGamer::route('/{record}/edit'),
        ];
    }
}
