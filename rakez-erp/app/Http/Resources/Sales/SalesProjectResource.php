<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'team_name' => $this->user->team ?? 'N/A',
            'project_description' => $this->notes,
            'location' => "{$this->city}, {$this->district}",
            'city' => $this->city,
            'district' => $this->district,
            'sales_status' => $this->sales_status ?? 'pending',
            'total_units' => $this->total_units ?? 0,
            'available_units' => $this->available_units ?? 0,
            'reserved_units' => $this->reserved_units ?? 0,
            'remaining_days' => $this->remaining_days,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
