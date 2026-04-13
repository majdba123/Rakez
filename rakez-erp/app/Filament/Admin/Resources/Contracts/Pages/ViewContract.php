<?php

namespace App\Filament\Admin\Resources\Contracts\Pages;

use App\Filament\Admin\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;
}
