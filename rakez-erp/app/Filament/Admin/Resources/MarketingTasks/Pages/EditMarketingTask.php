<?php

namespace App\Filament\Admin\Resources\MarketingTasks\Pages;

use App\Filament\Admin\Resources\MarketingTasks\MarketingTaskResource;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingTaskService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditMarketingTask extends EditRecord
{
    protected static string $resource = MarketingTaskResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        abort_unless($record instanceof MarketingTask, 404);

        return app(MarketingTaskService::class)->updateTask($record->id, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteTask')
                ->label('Delete')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->getRecord();

                    abort_unless($record instanceof MarketingTask, 404);

                    app(MarketingTaskService::class)->deleteTask($record->id);

                    $this->redirect(MarketingTaskResource::getUrl());
                }),
        ];
    }
}
