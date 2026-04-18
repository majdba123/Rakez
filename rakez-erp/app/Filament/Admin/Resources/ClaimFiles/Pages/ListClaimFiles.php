<?php

namespace App\Filament\Admin\Resources\ClaimFiles\Pages;

use App\Filament\Admin\Resources\ClaimFiles\ClaimFileResource;
use App\Filament\Admin\Resources\ClaimFiles\Pages\Concerns\HasClaimFileGenerationHeaderActions;
use Filament\Resources\Pages\ListRecords;

class ListClaimFiles extends ListRecords
{
    use HasClaimFileGenerationHeaderActions;

    protected static string $resource = ClaimFileResource::class;

    protected function getHeaderActions(): array
    {
        return $this->claimFileGenerationHeaderActions();
    }
}
