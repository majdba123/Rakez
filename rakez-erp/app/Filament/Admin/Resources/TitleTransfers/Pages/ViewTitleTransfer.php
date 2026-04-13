<?php

namespace App\Filament\Admin\Resources\TitleTransfers\Pages;

use App\Filament\Admin\Resources\TitleTransfers\TitleTransferResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTitleTransfer extends ViewRecord
{
    protected static string $resource = TitleTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
