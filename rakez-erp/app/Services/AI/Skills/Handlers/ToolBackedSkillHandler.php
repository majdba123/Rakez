<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\AI\ToolRegistry;

class ToolBackedSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $toolName = (string) ($definition['tool_name'] ?? '');
        if ($toolName === '') {
            return [
                'status' => 'error',
                'message' => 'Skill is misconfigured: tool_name is missing.',
                'reason' => 'skill_handler.missing_tool_name',
            ];
        }

        $toolInput = $input;
        unset($toolInput['context']);

        $response = $this->toolRegistry->execute($user, $toolName, $toolInput);
        $result = (array) ($response['result'] ?? []);
        $sources = (array) ($response['source_refs'] ?? []);

        if (($result['allowed'] ?? true) === false) {
            return [
                'status' => 'denied',
                'message' => (string) ($result['error'] ?? 'Permission denied for this skill.'),
                'reason' => 'tool_denied',
                'required_permission' => $result['required_permission'] ?? null,
                'sources' => $sources,
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'tool_denied',
                ],
            ];
        }

        if (isset($result['error'])) {
            return [
                'status' => 'error',
                'message' => (string) $result['error'],
                'reason' => 'tool_error',
                'sources' => $sources,
            ];
        }

        return [
            'status' => 'ok',
            'data' => (array) ($result['data'] ?? $result),
            'sources' => $sources,
            'confidence' => 'medium',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }
}
