<?php

namespace App\Filament\Admin\Resources\DirectPermissions\Pages;

use App\Filament\Admin\Resources\DirectPermissions\DirectPermissionResource;
use Filament\Resources\Pages\ListRecords;

class ListDirectPermissions extends ListRecords
{
    protected static string $resource = DirectPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
