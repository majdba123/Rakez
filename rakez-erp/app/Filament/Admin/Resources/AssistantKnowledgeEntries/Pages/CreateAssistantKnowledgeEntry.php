<?php

namespace App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource;
use App\Models\User;
use App\Services\AI\AssistantKnowledgeEntryService;
use Filament\Resources\Pages\CreateRecord;

class CreateAssistantKnowledgeEntry extends CreateRecord
{
    protected static string $resource = AssistantKnowledgeEntryResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return app(AssistantKnowledgeEntryService::class)->create($data, $actor);
    }
}
