<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\AiRagService;
use Illuminate\Support\Arr;

class RagSearchTool implements ToolContract
{
    public function __construct(
        private readonly AiRagService $ragService
    ) {}

    public function __invoke(User $user, array $args): array
    {
        $query = trim((string) Arr::get($args, 'query', ''));
        $filters = Arr::get($args, 'filters', []);
        $limit = min(10, max(1, (int) Arr::get($args, 'limit', 5)));

        if ($query === '') {
            return ['result' => ['sources' => [], 'count' => 0], 'source_refs' => []];
        }

        $sources = $this->ragService->search($user, $query, $filters, $limit);
        $sourceRefs = [];
        foreach ($sources as $s) {
            $sourceRefs[] = [
                'type' => $s['type'] ?? 'document',
                'title' => $s['title'] ?? '',
                'ref' => $s['ref'] ?? '',
            ];
        }

        return [
            'result' => ['sources' => $sources, 'count' => count($sources)],
            'source_refs' => $sourceRefs,
        ];
    }
}
