<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\AccessExplanationEngine;
use Illuminate\Support\Arr;

class ExplainAccessTool implements ToolContract
{
    public function __construct(
        private readonly AccessExplanationEngine $engine
    ) {}

    public function __invoke(User $user, array $args): array
    {
        $route = trim((string) Arr::get($args, 'route', ''));
        $entityType = Arr::get($args, 'entity_type');
        $entityId = Arr::has($args, 'entity_id') ? (int) Arr::get($args, 'entity_id') : null;

        $result = $this->engine->explainAccess($user, $route, $entityType, $entityId);

        $sourceRefs = [];
        if (! empty($result['suggested_routes'])) {
            foreach (array_slice($result['suggested_routes'], 0, 5) as $r) {
                $sourceRefs[] = ['type' => 'policy', 'title' => $r['label'] ?? $r['route'] ?? '', 'ref' => $r['route'] ?? ''];
            }
        }

        return [
            'result' => [
                'allowed' => $result['allowed'] ?? false,
                'missing_permissions' => $result['missing_permissions'] ?? [],
                'human_reason' => $result['human_reason'] ?? '',
                'suggested_routes' => $result['suggested_routes'] ?? [],
                'inputs' => ['route' => $route, 'entity_type' => $entityType, 'entity_id' => $entityId],
            ],
            'source_refs' => $sourceRefs,
        ];
    }
}
