<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\Contract;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class ContractProjectRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $projectId = isset($input['project_id']) ? (int) $input['project_id'] : 0;
        if ($projectId < 1) {
            return [
                'status' => 'needs_input',
                'message' => 'This skill requires an explicit `project_id` before execution.',
                'reason' => 'row_scope.project_id_required',
                'follow_up_questions' => ['Provide `project_id` to continue.'],
                'data' => [
                    'missing_fields' => ['project_id'],
                ],
            ];
        }

        $project = Contract::query()->find($projectId);
        if (! $project) {
            return [
                'status' => 'not_found',
                'message' => 'The requested project could not be found within your accessible scope.',
                'reason' => 'row_scope.project_not_found',
                'data' => [
                    'project_id' => $projectId,
                ],
            ];
        }

        if (! $user->can('contracts.view_all') && (int) $project->user_id !== (int) $user->id) {
            return [
                'status' => 'denied',
                'message' => 'The requested project is outside your accessible scope.',
                'reason' => 'row_scope.project_forbidden',
                'data' => [
                    'project_id' => $projectId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'contract',
                'record_id' => $projectId,
            ],
        ];
    }
}
