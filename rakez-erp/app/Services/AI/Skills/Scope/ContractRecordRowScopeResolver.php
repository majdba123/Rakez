<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\Contract;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class ContractRecordRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $contractId = isset($input['contract_id']) ? (int) $input['contract_id'] : 0;
        if ($contractId < 1) {
            return [
                'status' => 'needs_input',
                'message' => 'This skill requires an explicit `contract_id` before execution.',
                'reason' => 'row_scope.contract_id_required',
                'follow_up_questions' => ['Provide `contract_id` to continue.'],
                'data' => [
                    'missing_fields' => ['contract_id'],
                ],
            ];
        }

        $contract = Contract::query()->find($contractId);
        if (! $contract) {
            return [
                'status' => 'not_found',
                'message' => 'The requested contract could not be found within your accessible scope.',
                'reason' => 'row_scope.contract_not_found',
                'data' => [
                    'contract_id' => $contractId,
                ],
            ];
        }

        if (! $user->can('contracts.view_all') && (int) $contract->user_id !== (int) $user->id) {
            return [
                'status' => 'denied',
                'message' => 'The requested contract is outside your accessible scope.',
                'reason' => 'row_scope.contract_forbidden',
                'data' => [
                    'contract_id' => $contractId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'contract',
                'record_id' => $contractId,
            ],
        ];
    }
}
