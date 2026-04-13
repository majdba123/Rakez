<?php

namespace App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages;

use App\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListExclusiveProjectRequests extends ListRecords
{
    protected static string $resource = ExclusiveProjectRequestResource::class;
}
