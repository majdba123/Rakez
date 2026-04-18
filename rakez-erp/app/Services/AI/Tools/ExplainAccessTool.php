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
        if (! is_string($route) || mb_strlen($route) > 255) {
            return ToolResponse::invalidArguments('route must be a string under 255 characters.');
        }
        $route = preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $route);

        $entityType = $args['entity_type'] ?? null;
        if ($entityType !== null && (! is_string($entityType) || mb_strlen($entityType) > 60)) {
            return ToolResponse::invalidArguments('entity_type must be a string under 60 characters.');
        }

        $entityId = $args['entity_id'] ?? null;
        if ($entityId !== null) {
            $entityId = (int) $entityId;
        }

        $message = "access to {$route}";
        if ($entityType && $entityId) {
            $safeType = preg_replace('/[^a-zA-Z0-9_]/', '', $entityType);
            $message .= " for {$safeType} #{$entityId}";
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
