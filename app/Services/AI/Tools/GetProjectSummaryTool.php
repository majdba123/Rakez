<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class GetProjectSummaryTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        $projectId = (int) Arr::get($args, 'project_id', 0);
        if ($projectId <= 0) {
            return ['result' => ['error' => 'Invalid project_id'], 'source_refs' => []];
        }
        $contract = Contract::find($projectId);
        if (! $contract) {
            return ['result' => ['error' => 'Project not found'], 'source_refs' => []];
        }
        if (! Gate::forUser($user)->allows('view', $contract)) {
            return [
                'result' => ['error' => 'Access denied', 'allowed' => false],
                'source_refs' => [],
            ];
        }
        $summary = sprintf(
            'Project #%d: %s. Developer: %s. City: %s. Status: %s.',
            $contract->id,
            $contract->project_name ?? '—',
            $contract->developer_name ?? '—',
            $contract->city ?? '—',
            $contract->status ?? '—'
        );

        return [
            'result' => [
                'summary' => $summary,
                'project_id' => $contract->id,
                'project_name' => $contract->project_name,
                'developer_name' => $contract->developer_name,
                'status' => $contract->status,
                'inputs' => ['project_id' => $projectId],
            ],
            'source_refs' => [['type' => 'record', 'title' => 'Project: '.($contract->project_name ?? $contract->id), 'ref' => "contract/{$contract->id}"]],
        ];
    }
}
