<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\AI\VectorStore\VectorStoreInterface;
use Illuminate\Support\Facades\Gate;

class AiRagService
{
    public function __construct(
        private readonly VectorStoreInterface $vectorStore
    ) {}

    /**
     * RAG search with 4-step permission filter.
     * Returns only chunks the user is allowed to see.
     *
     * @return array<int, array{type: string, title: string, ref: string, excerpt?: string, link?: string}>
     */
    public function search(User $user, string $query, array $filters, int $limit): array
    {
        $topK = config('ai_assistant.v2.rag.rag_top_k', 10);
        $candidates = $this->vectorStore->search($query, $filters, $topK * 2);

        $permitted = [];
        foreach ($candidates as $hit) {
            $meta = $hit['meta'] ?? [];
            $access = $meta['access'] ?? [];

            // Step 2: permissions_any_of (deny-by-default if missing/empty)
            $permissionsAnyOf = $access['permissions_any_of'] ?? [];
            if (! is_array($permissionsAnyOf) || empty($permissionsAnyOf)) {
                continue;
            }
            $hasPermission = false;
            foreach ($permissionsAnyOf as $perm) {
                if ($user->can($perm)) {
                    $hasPermission = true;
                    break;
                }
            }
            if (! $hasPermission) {
                continue;
            }

            // Step 3: access.policy (model, ability, record_id)
            $policy = $access['policy'] ?? null;
            if (is_array($policy) && ! empty($policy)) {
                $modelClass = $policy['model'] ?? null;
                $ability = $policy['ability'] ?? 'view';
                $recordId = (int) ($policy['record_id'] ?? 0);
                if ($modelClass && $recordId && class_exists($modelClass)) {
                    $model = $modelClass::find($recordId);
                    if (! $model || ! Gate::forUser($user)->allows($ability, $model)) {
                        continue;
                    }
                }
            }

            // Filter out is_deleted
            if (! empty($meta['is_deleted'])) {
                continue;
            }

            $permitted[] = [
                'type' => $meta['type'] ?? 'document',
                'title' => $meta['title'] ?? 'Chunk',
                'ref' => (string) ($meta['source_uri'] ?? 'chunk:'.$hit['chunk_id']),
                'excerpt' => mb_substr($hit['content'], 0, 300),
                'link' => $meta['link'] ?? null,
            ];
            if (count($permitted) >= $limit) {
                break;
            }
        }

        return $permitted;
    }
}
