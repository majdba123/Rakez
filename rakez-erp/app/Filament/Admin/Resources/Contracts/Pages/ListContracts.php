<?php

namespace App\Filament\Admin\Resources\Contracts\Pages;

use App\Filament\Admin\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;
}
