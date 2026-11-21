<?php

namespace App\Filament\Resources\HouseWallets\Pages;

use App\Filament\Resources\HouseWallets\HouseWalletResource;
use Filament\Resources\Pages\ListRecords;

class ListHouseWallets extends ListRecords
{
    protected static string $resource = HouseWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
