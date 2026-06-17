<?php

namespace App\Filament\Resources\Gamers\Pages;

use App\Filament\Resources\Gamers\GamerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGamer extends EditRecord
{
    protected static string $resource = GamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
