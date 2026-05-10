<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecondPartyDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'real_estate_papers_url' => $this->real_estate_papers_url,
            'plans_equipment_docs_url' => $this->plans_equipment_docs_url,
            'project_logo_url' => $this->project_logo_url,
            'prices_units_url' => $this->prices_units_url,
            'marketing_license_url' => $this->marketing_license_url,
            'advertiser_section_url' => $this->advertiser_section_url,
            'advertiser_number' => $this->getAdvertiserSectionNumber(),
            'advertiser_number_expires_at' => $this->getAdvertiserSectionExpiryDate()?->toDateString(),
            'advertiser_number_remaining_days' => $this->getAdvertiserSectionRemainingDays(),
            'advertiser_number_expiry_status' => $this->getAdvertiserSectionExpiryStatus(),
            'contract_units_count_csv' => $this->whenLoaded('contractUnits', fn () => $this->contractUnits->count(), 0),
            'total_units_price_csv' => $this->whenLoaded('contractUnits', fn () => (float) $this->contractUnits->sum('price'), 0),
            'processed_by' => $this->when($this->processedByUser, [
                'id' => $this->processedByUser?->id,
                'name' => $this->processedByUser?->name,
                'type' => $this->processedByUser?->type,
            ]),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
