<?php

namespace App\Filament\Resources\BankDetails\Pages;

use App\Filament\Resources\BankDetails\BankDetailResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankDetail extends EditRecord
{
    protected static string $resource = BankDetailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
