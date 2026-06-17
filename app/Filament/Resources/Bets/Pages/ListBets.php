<?php

namespace App\Filament\Resources\Bets\Pages;

use App\Filament\Resources\Bets\BetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBets extends ListRecords
{
    protected static string $resource = BetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
