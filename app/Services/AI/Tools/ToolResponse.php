<?php

namespace App\Services\AI\Tools;

use App\Services\AI\GuardrailResult;

class ToolResponse
{
    /**
     * Build a successful tool response with structured fields.
     */
    public static function success(
        string $tool,
        array $inputs,
        array $outputs,
        array $sourceRefs = [],
        array $assumptions = []
    ): array {
        return [
            'result' => array_merge($outputs, [
                'inputs' => $inputs,
                'assumptions' => $assumptions,
            ]),
            'source_refs' => $sourceRefs,
        ];
    }

    /**
     * Build a permission-denied response.
     */
    public static function denied(string $permission): array
    {
        return [
            'result' => [
                'error' => 'Permission denied',
                'allowed' => false,
                'missing_permission' => $permission,
            ],
            'source_refs' => [],
        ];
    }

    /**
     * Append guardrail results to an existing response.
     */
    public static function withGuardrails(array $base, GuardrailResult ...$checks): array
    {
        $guardrails = [];
        foreach ($checks as $check) {
            $guardrails[] = $check->toArray();
        }

        $base['result']['guardrails'] = $guardrails;

        return $base;
    }
}
