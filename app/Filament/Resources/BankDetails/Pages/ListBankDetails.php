<?php

namespace App\Filament\Resources\BankDetails\Pages;

use App\Filament\Resources\BankDetails\BankDetailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankDetails extends ListRecords
{
    protected static string $resource = BankDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
