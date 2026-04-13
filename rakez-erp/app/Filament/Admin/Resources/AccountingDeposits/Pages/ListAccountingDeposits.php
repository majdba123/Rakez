<?php

namespace App\Filament\Admin\Resources\AccountingDeposits\Pages;

use App\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource;
use Filament\Resources\Pages\ListRecords;

class ListAccountingDeposits extends ListRecords
{
    protected static string $resource = AccountingDepositResource::class;
}
