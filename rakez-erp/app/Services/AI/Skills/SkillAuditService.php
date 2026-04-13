<?php

namespace App\Services\AI\Skills;

use App\Models\User;
use App\Services\AI\AiAuditService;
use Throwable;

class SkillAuditService
{
    public function __construct(
        private readonly AiAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function record(
        User $user,
        array $definition,
        string $status,
        array $input,
        array $output,
        ?string $correlationId = null,
    ): void {
        $action = (string) ($definition['audit']['action'] ?? 'skill_call');

        try {
            $this->auditService->record(
                $user,
                $action,
                'ai_skill',
                null,
                [
                    'skill_key' => $definition['skill_key'] ?? null,
                    'section_key' => $definition['section_key'] ?? null,
                    'status' => $status,
                    'input_keys' => array_keys($input),
                ],
                [
                    'status' => $status,
                    'confidence' => $output['confidence'] ?? null,
                    'sources_count' => count($output['sources'] ?? []),
                    'denied' => (bool) ($output['access_notes']['had_denied_request'] ?? false),
                ],
                $correlationId,
            );
        } catch (Throwable) {
            // Audit must not break the runtime path.
        }
    }
}
