<?php

namespace App\Http\Resources\ProjectManagement;

use App\Models\Contract;
use App\Services\ProjectManagement\ProjectManagementProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectListCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Contract $contract */
        $contract = $this->resource;
        $service = app(ProjectManagementProjectService::class);

        $firstMediaUrl = $contract->projectMedia->isNotEmpty()
            ? $contract->projectMedia->first()->url
            : $contract->project_image_url;

        $teamNames = $contract->teams->pluck('name')->filter()->values();
        $assignedTeam = $teamNames->isNotEmpty() ? $teamNames->implode(', ') : 'غير معين';

        $durationStatus = $service->getDurationStatusLabel($contract->id);

        return [
            'contract_id' => $contract->id,
            'project_name' => $contract->project_name,
            'city' => $contract->city,
            'district' => $contract->district,
            'subtitle' => trim(implode(', ', array_filter([$contract->city, $contract->district]))),
            'description' => $contract->notes ?? '',
            'project_image_url' => $firstMediaUrl ?? $contract->project_image_url,
            'status' => $contract->status,
            'active' => ! in_array($contract->status, ['rejected', 'completed'], true),
            'assigned_team' => $assignedTeam,
            'preparation_progress' => $service->getPreparationProgressPercent($contract),
            'units_sold_percent' => $service->getUnitsSoldPercent($contract),
            'duration_status' => [
                'label' => $durationStatus['label'],
                'days' => $durationStatus['days'],
                'status' => $durationStatus['status'],
            ],
        ];
    }
}
