<?php

namespace App\Filament\Admin\Resources\MarketingTasks\Pages;

use App\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource;
use App\Services\Marketing\MarketingTaskService;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingTask extends CreateRecord
{
    protected static string $resource = MarketingTaskResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $data['created_by'] = auth()->id();

        return app(MarketingTaskService::class)->createTask($data);
    }
}
