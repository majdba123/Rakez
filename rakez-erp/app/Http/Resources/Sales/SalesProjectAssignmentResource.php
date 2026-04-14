<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for project assignment card on "my" targets page.
 * Same shape as SalesTargetResource so frontend can render one list; item_type = 'project_assignment'.
 */
class SalesProjectAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->relationLoaded('contract') ? $this->contract : null;
        $contract?->loadMissing(['city', 'district']);
        $assignedBy = $this->relationLoaded('assignedBy') ? $this->assignedBy : null;

        return [
            'item_type' => 'project_assignment',
            'assignment_id' => $this->id,
            'target_id' => null,
            'contract_id' => $this->contract_id,
            'project_name' => $contract->project_name ?? 'N/A',
            'project_location' => [
                'city_id' => $contract?->city_id,
                'city_name' => $contract?->city?->name,
                'district_id' => $contract?->district_id,
                'district_name' => $contract?->district?->name,
            ],
            'must_sell_units_count' => null,
            'assigned_target_value' => null,
            'unit_number' => null,
            'contract_unit_ids' => [],
            'units' => [],
            'target_type' => null,
            'target_type_label_ar' => null,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => null,
            'status_label_ar' => null,
            'leader_notes' => null,
            'assigned_by' => $assignedBy->name ?? 'N/A',
            'marketer_id' => null,
            'marketer_name' => null,
        ];
    }
}
