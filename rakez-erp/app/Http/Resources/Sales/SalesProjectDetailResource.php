<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesProjectDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeAssignment = $this->salesProjectAssignments
            ? $this->salesProjectAssignments->first(fn($a) => $a->isActive())
            : null;

        $teamName = $activeAssignment?->leader?->team
            ?? $this->user?->team
            ?? 'N/A';

        $salesStatus = $this->sales_status ?? 'pending';
        $contractStatus = $this->status;
        $isReady = $contractStatus === 'completed';

        $cardFields = $this->relationLoaded('secondPartyData')
            ? SalesProjectResource::cardFieldsFromContract($this->resource, $salesStatus)
            : [
                'status_badge_ar' => $salesStatus === 'available' ? 'متاح' : 'غير متاح',
                'price_min' => null,
                'price_max' => null,
                'area_min_m2' => null,
                'area_max_m2' => null,
                'bedrooms_min' => null,
                'bedrooms_max' => null,
                'unit_type_label_ar' => null,
                'ad_code' => null,
            ];

        return array_merge([
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'developer_name' => $this->developer_name,
            'developer_number' => $this->developer_number,
            'city' => $this->city,
            'district' => $this->district,
            'location' => "{$this->city}, {$this->district}",
            'project_description' => $this->notes,
            'project_image_url' => SalesProjectResource::fullImageUrl($this->project_image_url),
            'contract_status' => $contractStatus,
            'is_ready' => $isReady,
            'sales_status' => $salesStatus,
            'project_status_label_ar' => SalesProjectResource::arabicStatusLabel($salesStatus, $isReady),
            'team_name' => $teamName,
            'emergency_contact_number' => $this->emergency_contact_number,
            'security_guard_number' => $this->security_guard_number,
            'total_units' => $this->total_units ?? 0,
            'available_units' => $this->available_units ?? 0,
            'reserved_units' => $this->reserved_units ?? 0,
            'sold_units' => $soldUnits = max(0, ($this->total_units ?? 0) - ($this->available_units ?? 0) - ($this->reserved_units ?? 0)),
            'sold_units_percent' => ($total = (int)($this->total_units ?? 0)) > 0 ? (int) round(($soldUnits / $total) * 100) : 0,
            'sold_units_label_ar' => 'وحدة مباعة',
            'preparation_progress_percent' => 0,
            'preparation_progress_label_ar' => 'N/A',
            'remaining_days' => $this->remaining_days,
            'montage_data' => $this->when($this->montageDepartment, function () {
                return [
                    'image_url' => SalesProjectResource::fullImageUrl($this->montageDepartment->image_url),
                    'video_url' => $this->montageDepartment->video_url,
                    'description' => $this->montageDepartment->description,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ], $cardFields);
    }
}
