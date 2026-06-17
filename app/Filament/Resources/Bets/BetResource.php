<?php

namespace App\Filament\Resources\Bets;

use App\Filament\Resources\Bets\Pages\CreateBet;
use App\Filament\Resources\Bets\Pages\EditBet;
use App\Filament\Resources\Bets\Pages\ListBets;
use App\Filament\Resources\Bets\Schemas\BetForm;
use App\Filament\Resources\Bets\Tables\BetsTable;
use App\Models\Bet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BetResource extends Resource
{
    protected static ?string $model = Bet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BetsTable::configure($table);
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
            'index' => ListBets::route('/'),
            'create' => CreateBet::route('/create'),
            'edit' => EditBet::route('/{record}/edit'),
        ];
    }
}
