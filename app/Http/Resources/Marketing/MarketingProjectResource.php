<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->contract;
        $contract->loadMissing(['projectMedia', 'units']);
        $info = $contract->info;

        $units = collect($contract->units ?? []);
        $availableUnits = $units->where('status', 'available');
        $pendingUnits = $units->where('status', 'pending');

        $locationParts = array_filter([$contract->city ?? null, $contract->district ?? null]);
        $location = $locationParts ? trim(implode(', ', $locationParts)) : null;

        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'project_name' => $contract->project_name ?? null,
            'developer_name' => $contract->developer_name ?? null,
            'location' => $location,
            'city' => $contract->city ?? null,
            'district' => $contract->district ?? null,
            'contract_number' => $info?->contract_number ?? null,
            'status' => $this->status,
            'team_leader' => $this->teamLeader->name ?? null,
            'units_count' => [
                'available' => $availableUnits->count(),
                'pending' => $pendingUnits->count(),
            ],
            'avg_unit_price' => $info ? (float) ($info->avg_property_value ?? 0) : 0,
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number ?? null,
            'advertiser_number_status' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'commission_percent' => $info ? (float) ($info->commission_percent ?? 0) : 0,
            'total_available_value' => (float) $availableUnits->sum('price'),
            'media_links' => $contract->projectMedia
                ->filter(function ($media) {
                    $isSupportedDepartment = in_array($media->department, ['montage', 'photography'], true);
                    $isSupportedType = in_array($media->type, ['image', 'video'], true);
                    $isAfterEditing = $media->approved_at !== null;

                    return ($isSupportedDepartment || $isSupportedType) && $isAfterEditing;
                })
                ->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
            'description' => $contract->notes ?? '',
            'duration_status' => app(\App\Services\Marketing\MarketingProjectService::class)->getContractDurationStatus($this->contract_id),
            'created_at' => $this->created_at,
        ];
    }
}
