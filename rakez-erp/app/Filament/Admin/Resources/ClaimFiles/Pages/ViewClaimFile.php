<?php

namespace App\Filament\Admin\Resources\ClaimFiles\Pages;

use App\Filament\Admin\Resources\ClaimFiles\ClaimFileResource;
use Filament\Resources\Pages\ViewRecord;

class ViewClaimFile extends ViewRecord
{
    protected static string $resource = ClaimFileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
