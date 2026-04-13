<?php

namespace App\Filament\Admin\Resources\AccountingClaimFiles\Pages;

use App\Filament\Admin\Resources\AccountingClaimFiles\AccountingClaimFileResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingClaimFile extends ViewRecord
{
    protected static string $resource = AccountingClaimFileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
