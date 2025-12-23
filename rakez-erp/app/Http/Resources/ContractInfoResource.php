<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'contract_number' => $this->contract_number,
            'first_party_name' => $this->first_party_name,
            'first_party_cr_number' => $this->first_party_cr_number,
            'first_party_signatory' => $this->first_party_signatory,
            'first_party_phone' => $this->first_party_phone,
            'first_party_email' => $this->first_party_email,
            'gregorian_date' => $this->gregorian_date?->toDateString(),
            'hijri_date' => $this->hijri_date,
            'contract_city' => $this->contract_city,
            'agreement_duration_days' => $this->agreement_duration_days,
            'commission_percent' => (float) $this->commission_percent,
            'commission_from' => $this->commission_from,
            'agency_number' => $this->agency_number,
            'agency_date' => $this->agency_date?->toDateString(),
            'avg_property_value' => (float) $this->avg_property_value,
            'release_date' => $this->release_date?->toDateString(),
            'second_party_developer_id' => $this->second_party_developer_id,
            'second_party_name' => $this->second_party_name,
            'second_party_address' => $this->second_party_address,
            'second_party_cr_number' => $this->second_party_cr_number,
            'second_party_signatory' => $this->second_party_signatory,
            'second_party_id_number' => $this->second_party_id_number,
            'second_party_role' => $this->second_party_role,
            'second_party_phone' => $this->second_party_phone,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
