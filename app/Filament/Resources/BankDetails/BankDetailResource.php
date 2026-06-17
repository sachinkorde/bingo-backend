<?php

namespace App\Filament\Resources\BankDetails;

use App\Filament\Resources\BankDetails\Pages\CreateBankDetail;
use App\Filament\Resources\BankDetails\Pages\EditBankDetail;
use App\Filament\Resources\BankDetails\Pages\ListBankDetails;
use App\Filament\Resources\BankDetails\Schemas\BankDetailForm;
use App\Filament\Resources\BankDetails\Tables\BankDetailsTable;
use App\Models\BankDetail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BankDetailResource extends Resource
{
    protected static ?string $model = BankDetail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BankDetailForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankDetailsTable::configure($table);
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
            'index' => ListBankDetails::route('/'),
            'create' => CreateBankDetail::route('/create'),
            'edit' => EditBankDetail::route('/{record}/edit'),
        ];
    }
}
