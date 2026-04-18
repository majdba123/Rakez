<?php

namespace App\Services\AI;

use App\Models\AiPromptVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PromptVersionManager
{
    private static ?bool $tableExists = null;

    public function __construct(
        private readonly PromptSafetyValidator $safetyValidator = new PromptSafetyValidator,
    ) {}

    /**
     * @return array{content: string, version_id: int|null, version: int|null}
     */
    public function resolve(string $promptKey, string $fallbackContent, ?int $createdBy = null): array
    {
        if (! $this->tableExists()) {
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
            // Safety guard: DB-stored prompts must retain required safety anchors.
            // If an admin stripped core safety rules, fall back to hardcoded content.
            if (! $this->safetyValidator->validate($active->content)) {
                $missing = $this->safetyValidator->missingAnchors($active->content);
                Log::warning('AI prompt version rejected by safety validator — falling back to hardcoded prompt', [
                    'prompt_key' => $promptKey,
                    'version_id' => $active->id,
                    'missing_anchors' => $missing,
                    'oversized' => mb_strlen($active->content) > 32_000,
                ]);

                return [
                    'content' => $fallbackContent,
                    'version_id' => null,
                    'version' => null,
                ];
            }

            return [
                'content' => $active->content,
                'version_id' => $active->id,
                'version' => $active->version,
            ];
        }

        // Use DB transaction with locking to prevent race condition on version creation
        $created = DB::transaction(function () use ($promptKey, $fallbackContent, $createdBy) {
            $nextVersion = (int) AiPromptVersion::query()
                ->forKey($promptKey)
                ->lockForUpdate()
                ->max('version') + 1;

            return AiPromptVersion::query()->create([
                'prompt_key' => $promptKey,
                'version' => max(1, $nextVersion),
                'content' => $fallbackContent,
                'created_by' => $createdBy,
                'is_active' => true,
            ]);
        });

        return [
            'content' => $created->content,
            'version_id' => $created->id,
            'version' => $created->version,
        ];
    }

    private function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        return self::$tableExists = Cache::remember(
            'ai:prompt_versions_table_exists',
            300,
            fn () => Schema::hasTable('ai_prompt_versions'),
        );
    }
}
