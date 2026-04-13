<?php

namespace App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAssistantKnowledgeEntry extends ViewRecord
{
    protected static string $resource = AssistantKnowledgeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
