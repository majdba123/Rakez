<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    /**
     * Transform the resource into an array for show/detail view.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city' => $this->city,
            'district' => $this->district,
            'units_count' => $this->units_count,
            'unit_type' => $this->unit_type,
            'average_unit_price' => (float) $this->average_unit_price,
            'total_units_value' => (float) $this->total_units_value,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'info' => new ContractInfoResource($this->whenLoaded('info')),
        ];
    }
}
