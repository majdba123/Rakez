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

        $message = "access to {$route}";
        if ($entityType && $entityId) {
            $message .= " for {$entityType} #{$entityId}";
        }

        $explanation = $this->engine->explain($user, $message);

        if ($explanation === null) {
            return ToolResponse::insufficientData('tool_explain_access', $args, [
                'tool_kind' => 'access_explanation',
                'summary' => 'لم يُعثر على قاعدة وصول محدّدة لهذا المسار في محرّك الصلاحيات؛ قد يحتاج المسار إلى إعداد إضافي.',
                'missing_data' => ['access_rule_match'],
            ], [], 'access_engine');
        }

        return ToolResponse::success('tool_explain_access', $args, [
            'tool_kind' => 'access_explanation',
            'allowed' => $explanation['allowed'],
            'reason_code' => $explanation['reason_code'],
            'message' => $explanation['message'],
            'steps' => $explanation['steps'] ?? [],
        ], [], [], 'access_engine');
    }
}
