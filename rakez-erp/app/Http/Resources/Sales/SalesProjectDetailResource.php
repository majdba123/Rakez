<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesProjectDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city' => $this->city,
            'district' => $this->district,
            'project_image_url' => $this->project_image_url,
            'sales_status' => $this->sales_status ?? 'pending',
            'emergency_contact_number' => $this->emergency_contact_number,
            'security_guard_number' => $this->security_guard_number,
            'total_units' => $this->total_units ?? 0,
            'available_units' => $this->available_units ?? 0,
            'reserved_units' => $this->reserved_units ?? 0,
            'montage_data' => $this->when($this->montageDepartment, function () {
                return [
                    'image_url' => $this->montageDepartment->image_url,
                    'video_url' => $this->montageDepartment->video_url,
                    'description' => $this->montageDepartment->description,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
