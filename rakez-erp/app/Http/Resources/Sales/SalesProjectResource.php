<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeAssignment = $this->salesProjectAssignments
            ? $this->salesProjectAssignments->first(fn($a) => $a->isActive())
            : null;

        $teamName = $activeAssignment?->leader?->team
            ?? $this->user?->team
            ?? 'N/A';

        $status = $this->sales_status ?? 'pending';

        return [
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'team_name' => $teamName,
            'project_description' => $this->notes,
            'location' => "{$this->city}, {$this->district}",
            'city' => $this->city,
            'district' => $this->district,
            'sales_status' => $status,
            'project_status_label_ar' => $status === 'available' ? 'متاح' : 'قيد الانتظار',
            'total_units' => $this->total_units ?? 0,
            'available_units' => $this->available_units ?? 0,
            'reserved_units' => $this->reserved_units ?? 0,
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
