<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\AiCall;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class AiCallRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $callId = isset($input['call_id']) ? (int) $input['call_id'] : 0;
        if ($callId < 1) {
            return [
                'status' => 'needs_input',
                'message' => 'This skill requires an explicit `call_id` before execution.',
                'reason' => 'row_scope.ai_call_id_required',
                'follow_up_questions' => ['Provide `call_id` to continue.'],
                'data' => [
                    'missing_fields' => ['call_id'],
                ],
            ];
        }

        $call = AiCall::query()->find($callId);
        if (! $call) {
            return [
                'status' => 'not_found',
                'message' => 'The requested AI call could not be found within your accessible scope.',
                'reason' => 'row_scope.ai_call_not_found',
                'data' => [
                    'call_id' => $callId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'ai_call',
                'record_id' => $callId,
            ],
        ];
    }
}
