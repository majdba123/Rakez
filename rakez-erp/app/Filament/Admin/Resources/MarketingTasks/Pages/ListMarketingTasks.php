<?php

namespace App\Filament\Admin\Resources\MarketingTasks\Pages;

use App\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMarketingTasks extends ListRecords
{
    protected static string $resource = MarketingTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
