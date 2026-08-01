<?php

namespace App\Filament\Resources\AppVersions\Pages;

use App\Filament\Resources\AppVersions\AppVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppVersion extends EditRecord
{
    protected static string $resource = AppVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
