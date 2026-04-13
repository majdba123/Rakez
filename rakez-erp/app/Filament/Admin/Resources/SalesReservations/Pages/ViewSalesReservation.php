<?php

namespace App\Filament\Admin\Resources\SalesReservations\Pages;

use App\Filament\Admin\Resources\SalesReservations\SalesReservationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesReservation extends ViewRecord
{
    protected static string $resource = SalesReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
