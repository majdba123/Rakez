<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contract = $this->contract;
        $info = $contract->info;

        $units = \App\Models\ContractUnit::where('second_party_data_id', $contract->secondPartyData->id ?? 0)->get();
        $availableUnits = $units->where('status', 'available');
        $pendingUnits = $units->where('status', 'pending');

        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'project_name' => $contract->project_name ?? null,
            'developer_name' => $contract->developer_name ?? null,
            'status' => $this->status,
            'team_leader' => $this->teamLeader->name ?? null,
            'units_count' => [
                'available' => $availableUnits->count(),
                'pending' => $pendingUnits->count(),
            ],
            'avg_unit_price' => $info?->avg_property_value ?? 0,
<<<<<<< HEAD
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number,
            'advertiser_number_status' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'commission_percent' => $info?->commission_percent ?? 0,
            'total_available_value' => $availableUnits->sum('price'),
            'media_links' => $contract->projectMedia
                ->filter(function ($media) {
                    $isSupportedDepartment = in_array($media->department, ['montage', 'photography'], true);
                    $isSupportedType = in_array($media->type, ['image', 'video'], true);

                    return $isSupportedDepartment || $isSupportedType;
                })
                ->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
=======
            'advertiser_number' => $info?->agency_number ?? 'Pending',
            'commission_percent' => $info?->commission_percent ?? 0,
            'total_available_value' => $availableUnits->sum('price'),
            'media_links' => $contract->projectMedia->map(fn($m) => ['type' => $m->type, 'url' => $m->url]),
>>>>>>> parent of 29c197a (Add edits)
            'description' => $contract->notes ?? '',
            'created_at' => $this->created_at,
        ];
    }
}
