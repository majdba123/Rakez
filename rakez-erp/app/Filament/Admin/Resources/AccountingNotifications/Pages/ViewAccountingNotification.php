<?php

namespace App\Filament\Admin\Resources\AccountingNotifications\Pages;

use App\Filament\Admin\Resources\AccountingNotifications\AccountingNotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAccountingNotification extends ViewRecord
{
    protected static string $resource = AccountingNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
