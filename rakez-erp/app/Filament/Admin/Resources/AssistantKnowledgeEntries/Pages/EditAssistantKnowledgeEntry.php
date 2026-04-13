<?php

namespace App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource;
use App\Models\AssistantKnowledgeEntry;
use App\Models\User;
use App\Services\AI\AssistantKnowledgeEntryService;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditAssistantKnowledgeEntry extends EditRecord
{
    protected static string $resource = AssistantKnowledgeEntryResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User && $record instanceof AssistantKnowledgeEntry, 403);

        return app(AssistantKnowledgeEntryService::class)->update($record, $data, $actor);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteEntry')
                ->label('Delete')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $record = $this->getRecord();

                    abort_unless($record instanceof AssistantKnowledgeEntry, 404);

                    app(AssistantKnowledgeEntryService::class)->delete($record);

                    $this->redirect(AssistantKnowledgeEntryResource::getUrl());
                }),
        ];
    }
}
