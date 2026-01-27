<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesTargetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'target_id' => $this->id,
            'project_name' => $this->contract->project_name ?? 'N/A',
            'unit_number' => $this->contractUnit->unit_number ?? null,
            'target_type' => $this->target_type,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'status' => $this->status,
            'leader_notes' => $this->leader_notes,
            'assigned_by' => $this->leader->name ?? 'N/A',
        ];
    }
}
