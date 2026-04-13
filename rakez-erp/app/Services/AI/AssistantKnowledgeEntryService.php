<?php

namespace App\Services\AI;

use App\Models\AssistantKnowledgeEntry;
use App\Models\User;
use Illuminate\Support\Arr;

class AssistantKnowledgeEntryService
{
    public function create(array $data, User $actor): AssistantKnowledgeEntry
    {
        return AssistantKnowledgeEntry::create($this->payload($data, $actor));
    }

    public function update(AssistantKnowledgeEntry $entry, array $data, User $actor): AssistantKnowledgeEntry
    {
        $entry->update($this->payload($data, $actor));

        return $entry->fresh();
    }

    public function delete(AssistantKnowledgeEntry $entry): void
    {
        $entry->delete();
    }

    protected function payload(array $data, User $actor): array
    {
        return [
            ...Arr::except($data, ['updated_by']),
            'updated_by' => $actor->id,
        ];
    }
}
