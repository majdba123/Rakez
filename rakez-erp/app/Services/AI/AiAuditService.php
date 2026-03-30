<?php

namespace App\Services\AI;

use App\Models\AiAuditEntry;
use App\Models\User;
use Illuminate\Support\Str;

class AiAuditService
{
    /**
     * Record an AI audit trail entry.
     */
    public function record(
        User $user,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $input = [],
        array $output = [],
        ?string $correlationId = null,
    ): AiAuditEntry {
        return AiAuditEntry::create([
            'user_id' => $user->id,
            'correlation_id' => $correlationId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'input_summary' => Str::limit(json_encode($input, JSON_UNESCAPED_UNICODE), 1000),
            'output_summary' => Str::limit(json_encode($output, JSON_UNESCAPED_UNICODE), 1000),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function recordByUserId(
        int $userId,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $input = [],
        array $output = [],
        ?string $correlationId = null,
    ): AiAuditEntry {
        return AiAuditEntry::create([
            'user_id' => $userId,
            'correlation_id' => $correlationId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'input_summary' => Str::limit(json_encode($input, JSON_UNESCAPED_UNICODE), 1000),
            'output_summary' => Str::limit(json_encode($output, JSON_UNESCAPED_UNICODE), 1000),
            'ip_address' => request()->ip(),
        ]);
    }
}
