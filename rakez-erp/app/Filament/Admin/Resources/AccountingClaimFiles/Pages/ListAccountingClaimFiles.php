<?php

namespace App\Filament\Admin\Resources\AccountingClaimFiles\Pages;

use App\Filament\Admin\Resources\AccountingClaimFiles\AccountingClaimFileResource;
use App\Filament\Admin\Resources\ClaimFiles\Pages\Concerns\HasClaimFileGenerationHeaderActions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingClaimFiles extends ListRecords
{
    use HasClaimFileGenerationHeaderActions;

    protected static string $resource = AccountingClaimFileResource::class;

    protected function getHeaderActions(): array
    {
        return $this->claimFileGenerationHeaderActions();
    }
}
