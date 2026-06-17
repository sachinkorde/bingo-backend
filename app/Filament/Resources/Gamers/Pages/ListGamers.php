<?php

namespace App\Filament\Resources\Gamers\Pages;

use App\Filament\Resources\Gamers\GamerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGamers extends ListRecords
{
    protected static string $resource = GamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
