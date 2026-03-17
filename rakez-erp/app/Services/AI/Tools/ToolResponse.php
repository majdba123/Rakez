<?php

namespace App\Services\AI\Tools;

use App\Services\AI\GuardrailCheck;

class ToolResponse
{
    /**
     * Build a successful tool response.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, mixed>  $data
     * @param  array<int, array{type: string, title: string, ref: string}>  $sourceRefs
     * @param  array<int, string>  $notes
     * @return array{result: array, source_refs: array}
     */
    public static function success(
        string $toolName,
        array $inputs,
        array $data,
        array $sourceRefs = [],
        array $notes = [],
    ): array {
        $result = [
            'tool' => $toolName,
            'inputs' => $inputs,
            'data' => $data,
        ];

        if (! empty($notes)) {
            $result['notes'] = $notes;
        }

        return [
            'result' => $result,
            'source_refs' => $sourceRefs,
        ];
    }

    /**
     * Build a denied response when the user lacks permission.
     *
     * @return array{result: array{allowed: false, error: string, required_permission: string}, source_refs: array}
     */
    public static function denied(string $permission): array
    {
        return [
            'result' => [
                'allowed' => false,
                'error' => "Permission denied: you need '{$permission}' to use this tool.",
                'required_permission' => $permission,
            ],
            'source_refs' => [],
        ];
    }

    /**
     * Append guardrail check results to an existing tool response.
     *
     * @param  array{result: array, source_refs: array}  $response
     * @return array{result: array, source_refs: array}
     */
    public static function withGuardrails(array $response, GuardrailCheck $check): array
    {
        if (! isset($response['result']['guardrails'])) {
            $response['result']['guardrails'] = [];
        }

        $response['result']['guardrails'][] = $check->toArray();

        return $response;
    }

    /**
     * Build an error response.
     *
     * @return array{result: array{error: string}, source_refs: array}
     */
    public static function error(string $message): array
    {
        return [
            'result' => ['error' => $message],
            'source_refs' => [],
        ];
    }
}
