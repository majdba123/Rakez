<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\User;

class GetContractStatusTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $contractId = $args['contract_id'] ?? null;
        if (! $contractId) {
            return ToolResponse::error('contract_id is required.');
        }

        $contract = Contract::find($contractId);

        if (! $contract) {
            return ToolResponse::error("Contract #{$contractId} not found.");
        }

        if (! $user->can('contracts.view_all') && $contract->user_id !== $user->id) {
            return ToolResponse::denied('contracts.view_all');
        }

        $data = [
            'id' => $contract->id,
            'project_name' => $contract->project_name,
            'developer_name' => $contract->developer_name,
            'status' => $contract->status,
            'is_closed' => (bool) $contract->is_closed,
            'commission_percent' => $contract->commission_percent,
            'commission_from' => $contract->commission_from,
            'notes' => $contract->notes,
            'created_at' => $contract->created_at?->toDateTimeString(),
            'updated_at' => $contract->updated_at?->toDateTimeString(),
        ];

        return ToolResponse::success('tool_get_contract_status', ['contract_id' => $contractId], $data, [
            ['type' => 'record', 'title' => "Contract: {$contract->project_name}", 'ref' => "contract:{$contract->id}"],
        ]);
    }
}
