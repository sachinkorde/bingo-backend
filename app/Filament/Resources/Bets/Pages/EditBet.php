<?php

namespace App\Filament\Resources\Bets\Pages;

use App\Filament\Resources\Bets\BetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBet extends EditRecord
{
    protected static string $resource = BetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
