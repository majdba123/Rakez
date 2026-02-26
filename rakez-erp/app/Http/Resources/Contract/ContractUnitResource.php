<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array (contract unit details from CSV)
     * وحدات العقد
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'second_party_data_id' => $this->second_party_data_id,
            'contract_id' => $this->secondPartyData?->contract_id,
            'unit_type' => $this->unit_type,
            'unit_number' => $this->unit_number,
            'status' => $this->status,
            'price' => (float) $this->price,
            'area' => $this->area,
            'floor' => $this->floor ?? null,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

