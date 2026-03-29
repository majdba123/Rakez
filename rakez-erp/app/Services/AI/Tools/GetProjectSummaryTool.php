<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\User;

class GetProjectSummaryTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('contracts.view')) {
            return ToolResponse::denied('contracts.view');
        }

        $projectId = $args['project_id'] ?? null;
        if (! $projectId) {
            return ToolResponse::error('project_id is required.');
        }

        $project = Contract::with(['units', 'reservations', 'city', 'district'])->find($projectId);

        if (! $project) {
            return ToolResponse::error("Project #{$projectId} not found.");
        }

        if (! $user->can('contracts.view_all') && $project->user_id !== $user->id) {
            return ToolResponse::denied('contracts.view_all');
        }

        $units = $project->units ?? [];
        $totalUnits = is_array($units) ? count($units) : 0;

        $data = [
            'id' => $project->id,
            'project_name' => $project->project_name,
            'developer_name' => $project->developer_name,
            'city' => $project->city?->name,
            'district' => $project->district?->name,
            'status' => $project->status,
            'is_off_plan' => $project->is_off_plan,
            'total_units' => $totalUnits,
            'commission_percent' => $project->commission_percent,
            'commission_from' => $project->commission_from,
            'is_closed' => $project->is_closed,
            'reservations_count' => $project->reservations?->count() ?? 0,
            'created_at' => $project->created_at?->toDateTimeString(),
        ];

        return ToolResponse::success('tool_get_project_summary', ['project_id' => $projectId], $data, [
            ['type' => 'record', 'title' => "Project: {$project->project_name}", 'ref' => "project:{$project->id}"],
        ]);
    }
}
