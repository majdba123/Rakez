<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\AccessExplanationEngine;

class ExplainAccessTool implements ToolContract
{
    public function __construct(
        private readonly AccessExplanationEngine $engine,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $route = $args['route'] ?? '';
        $entityType = $args['entity_type'] ?? null;
        $entityId = $args['entity_id'] ?? null;

        // Build a message from the route/entity info for the engine
        $message = "access to {$route}";
        if ($entityType && $entityId) {
            $message .= " for {$entityType} #{$entityId}";
        }

        $explanation = $this->engine->explain($user, $message);

        if ($explanation === null) {
            return ToolResponse::success('tool_explain_access', $args, [
                'explanation' => 'Unable to determine access information for this route.',
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->toArray(),
                'roles' => $user->getRoleNames()->toArray(),
            ]);
        }

        return ToolResponse::success('tool_explain_access', $args, [
            'allowed' => $explanation['allowed'],
            'reason_code' => $explanation['reason_code'],
            'message' => $explanation['message'],
            'steps' => $explanation['steps'] ?? [],
            'current_permissions' => $user->getAllPermissions()->pluck('name')->values()->toArray(),
            'current_roles' => $user->getRoleNames()->toArray(),
        ]);
    }
}
