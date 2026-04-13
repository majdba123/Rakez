<?php

namespace App\Filament\Admin\Resources\EffectiveAccess\Pages;

use App\Filament\Admin\Resources\EffectiveAccess\EffectiveAccessResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEffectiveAccess extends ViewRecord
{
    protected static string $resource = EffectiveAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
