<?php

namespace App\Filament\Admin\Resources\GovernanceAuditLogs\Pages;

use App\Filament\Admin\Resources\GovernanceAuditLogs\GovernanceAuditLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGovernanceAuditLog extends ViewRecord
{
    protected static string $resource = GovernanceAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
