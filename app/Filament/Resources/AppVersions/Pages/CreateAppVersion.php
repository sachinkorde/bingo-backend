<?php

namespace App\Filament\Resources\AppVersions\Pages;

use App\Filament\Resources\AppVersions\AppVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppVersion extends CreateRecord
{
    protected static string $resource = AppVersionResource::class;
}
