<?php

namespace App\Services\AI;

use App\Models\AiPromptVersion;
use Illuminate\Support\Facades\Schema;

class PromptVersionManager
{
    /**
     * @return array{content: string, version_id: int|null, version: int|null}
     */
    public function resolve(string $promptKey, string $fallbackContent, ?int $createdBy = null): array
    {
        if (! Schema::hasTable('ai_prompt_versions')) {
            return [
                'content' => $fallbackContent,
                'version_id' => null,
                'version' => null,
            ];
        }

        $active = AiPromptVersion::query()
            ->forKey($promptKey)
            ->active()
            ->orderByDesc('version')
            ->first();

        if ($active) {
            return [
                'content' => $active->content,
                'version_id' => $active->id,
                'version' => $active->version,
            ];
        }

        $nextVersion = (int) AiPromptVersion::query()
            ->forKey($promptKey)
            ->max('version') + 1;

        $created = AiPromptVersion::query()->create([
            'prompt_key' => $promptKey,
            'version' => max(1, $nextVersion),
            'content' => $fallbackContent,
            'created_by' => $createdBy,
            'is_active' => true,
        ]);

        return [
            'content' => $created->content,
            'version_id' => $created->id,
            'version' => $created->version,
        ];
    }
}
