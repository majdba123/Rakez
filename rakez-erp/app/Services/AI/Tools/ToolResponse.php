<?php

namespace App\Services\AI\Tools;

use App\Services\AI\GuardrailCheck;

class ToolResponse
{
    /**
     * Merge successful payload fields; tool envelope {@see ToolResultStatus} and data_source always win.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withPayloadDefaults(array $data, string $dataSource = 'database'): array
    {
        // Envelope keys must win: business payloads often include a `status` field that
        // would otherwise overwrite ToolResultStatus::Success.
        return array_merge($data, [
            'status' => ToolResultStatus::Success->value,
            'data_source' => $dataSource,
        ]);
    }

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
        string $defaultDataSource = 'database',
    ): array {
        $result = [
            'tool' => $toolName,
            'inputs' => $inputs,
            'data' => self::withPayloadDefaults($data, $defaultDataSource),
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
     * Successful payload with explicit insufficient-data semantics.
     *
     * @param  array<string, mixed>  $data
     * @return array{result: array, source_refs: array}
     */
    public static function insufficientData(
        string $toolName,
        array $inputs,
        array $data,
        array $sourceRefs = [],
        string $dataSource = 'database',
    ): array {
        $data = array_merge($data, [
            'status' => ToolResultStatus::InsufficientData->value,
            'data_source' => $dataSource,
        ]);

        return [
            'result' => [
                'tool' => $toolName,
                'inputs' => $inputs,
                'data' => $data,
            ],
            'source_refs' => $sourceRefs,
        ];
    }

    /**
     * Build a denied response when the user lacks permission.
     *
     * @return array{result: array, source_refs: array}
     */
    public static function denied(string $permission): array
    {
        return [
            'result' => [
                'status' => ToolResultStatus::Denied->value,
                'allowed' => false,
                // User-facing Arabic; technical slug stays in required_permission for clients/audits.
                'error' => 'لا تملك الصلاحية الكافية لتنفيذ هذه العملية في النظام.',
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
     * Build a generic error response (unexpected failures).
     *
     * @return array{result: array, source_refs: array}
     */
    public static function error(string $message): array
    {
        return [
            'result' => [
                'status' => ToolResultStatus::Error->value,
                'error' => $message,
            ],
            'source_refs' => [],
        ];
    }

    /**
     * Validation / input contract failure (not permission denied).
     *
     * @return array{result: array, source_refs: array}
     */
    public static function invalidArguments(string $message): array
    {
        return [
            'result' => [
                'status' => ToolResultStatus::InvalidArguments->value,
                'error' => $message,
            ],
            'source_refs' => [],
        ];
    }

    /**
     * Unknown topic, report, action, or operation not supported by this tool.
     *
     * @return array{result: array, source_refs: array}
     */
    public static function unsupportedOperation(string $message): array
    {
        return [
            'result' => [
                'status' => ToolResultStatus::UnsupportedOperation->value,
                'error' => $message,
            ],
            'source_refs' => [],
        ];
    }
}
