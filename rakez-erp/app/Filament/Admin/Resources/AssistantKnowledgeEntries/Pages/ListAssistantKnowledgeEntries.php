<?php

namespace App\Filament\Admin\Resources\AssistantKnowledgeEntries\Pages;

use App\Filament\Admin\Resources\AssistantKnowledgeEntries\AssistantKnowledgeEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListAssistantKnowledgeEntries extends ListRecords
{
    protected static string $resource = AssistantKnowledgeEntryResource::class;
}
