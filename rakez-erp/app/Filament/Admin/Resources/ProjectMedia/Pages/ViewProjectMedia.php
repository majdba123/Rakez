<?php

namespace App\Filament\Admin\Resources\ProjectMedia\Pages;

use App\Filament\Admin\Resources\ProjectMedia\ProjectMediaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProjectMedia extends ViewRecord
{
    protected static string $resource = ProjectMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
