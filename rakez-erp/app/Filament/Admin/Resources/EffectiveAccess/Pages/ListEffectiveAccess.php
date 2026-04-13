<?php

namespace App\Filament\Admin\Resources\EffectiveAccess\Pages;

use App\Filament\Admin\Resources\EffectiveAccess\EffectiveAccessResource;
use Filament\Resources\Pages\ListRecords;

class ListEffectiveAccess extends ListRecords
{
    protected static string $resource = EffectiveAccessResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
