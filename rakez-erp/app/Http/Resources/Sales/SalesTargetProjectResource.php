<?php

namespace App\Http\Resources\Sales;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SalesTargetProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Contract $contract */
        $contract = $this->resource;
        $contract->loadMissing(['city', 'district']);

        return [
            'item_type' => 'project_assignment',
            'assignment_id' => null,
            'target_id' => null,
            'contract_id' => $contract->id,
            'project_name' => $contract->project_name,
            'project_location' => [
                'city_id' => $contract->city_id,
                'city_name' => $contract->city?->name,
                'district_id' => $contract->district_id,
                'district_name' => $contract->district?->name,
            ],
            'must_sell_units_count' => null,
            'assigned_target_value' => null,
            'unit_number' => null,
            'contract_unit_ids' => [],
            'units' => [],
            'target_type' => null,
            'target_type_label_ar' => null,
            'start_date' => $contract->team_attached_at
                ? Carbon::parse($contract->team_attached_at)->toDateString()
                : $contract->created_at?->toDateString(),
            'end_date' => null,
            'status' => null,
            'status_label_ar' => null,
            'leader_notes' => null,
            'leader_id' => null,
            'assigned_by' => 'Project Management',
            'marketer_id' => null,
            'marketer_name' => null,
        ];
    }
}
