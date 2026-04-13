<?php

namespace App\Filament\Admin\Resources\AiAuditEntries\Pages;

use App\Filament\Admin\Resources\AiAuditEntries\AiAuditEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAiAuditEntries extends ListRecords
{
    protected static string $resource = AiAuditEntryResource::class;
}
