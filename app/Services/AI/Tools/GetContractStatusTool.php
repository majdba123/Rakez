<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GetContractStatusTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        $contractId = (int) Arr::get($args, 'contract_id', 0);
        if ($contractId <= 0) {
            return ['result' => ['error' => 'Invalid contract_id'], 'source_refs' => []];
        }
        $contract = Contract::find($contractId);
        if (! $contract) {
            return ['result' => ['error' => 'Contract not found'], 'source_refs' => []];
        }
        if (! Gate::forUser($user)->allows('view', $contract)) {
            return [
                'result' => ['error' => 'Access denied', 'allowed' => false],
                'source_refs' => [],
            ];
        }

        return [
            'result' => [
                'contract_id' => $contract->id,
                'status' => $contract->status ?? '—',
                'project_name' => $contract->project_name ?? '—',
                'created_at' => $contract->created_at?->toIso8601String(),
                'updated_at' => $contract->updated_at?->toIso8601String(),
            ],
            'source_refs' => [['type' => 'record', 'title' => 'Contract: '.($contract->project_name ?? $contract->id), 'ref' => "contract/{$contract->id}"]],
        ];
    }
}
