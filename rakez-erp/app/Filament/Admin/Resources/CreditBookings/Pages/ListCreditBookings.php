<?php

namespace App\Filament\Admin\Resources\CreditBookings\Pages;

use App\Filament\Admin\Resources\CreditBookings\CreditBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListCreditBookings extends ListRecords
{
    protected static string $resource = CreditBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
