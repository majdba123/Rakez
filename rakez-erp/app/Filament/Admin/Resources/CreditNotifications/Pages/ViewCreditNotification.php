<?php

namespace App\Filament\Admin\Resources\CreditNotifications\Pages;

use App\Filament\Admin\Resources\CreditNotifications\CreditNotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCreditNotification extends ViewRecord
{
    protected static string $resource = CreditNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
