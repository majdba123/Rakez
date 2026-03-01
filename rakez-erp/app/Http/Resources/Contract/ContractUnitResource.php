<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractUnitResource extends JsonResource
{
    /**
     * Transform the resource into an array (contract unit details from CSV).
     * وحدات العقد — الحقول المتوقعة من الواجهة: unit_number, price, area_m2, bedrooms, bathrooms, floor, إلخ.
     */
    public function toArray(Request $request): array
    {
        $area = $this->area !== null && $this->area !== '' ? (float) $this->area : null;
        $totalArea = $this->total_area_m2 ?? $area;

        return [
            'id' => $this->id,
            'unit_id' => $this->id,
            'second_party_data_id' => $this->second_party_data_id,
            'contract_id' => $this->secondPartyData?->contract_id,
            'unit_type' => $this->unit_type,
            'unit_number' => $this->unit_number,
            'status' => $this->status,
            'price' => (float) $this->price,
            'total_price' => (float) $this->price,
            'area' => $area,
            'area_m2' => $area,
            'bedrooms' => $this->bedrooms ?? null,
            'rooms' => $this->bedrooms ?? null,
            'bathrooms' => $this->bathrooms ?? null,
            'bathrooms_count' => $this->bathrooms ?? null,
            'floor' => $this->floor ?? null,
            'private_area' => $this->private_area_m2 ?? null,
            'private_area_m2' => $this->private_area_m2 ?? null,
            'balcony_area' => $this->private_area_m2 ?? null,
            'total_area' => $totalArea,
            'total_area_m2' => $this->total_area_m2 ?? null,
            'facade' => $this->facade ?? null,
            'view' => $this->facade ?? null,
            'orientation' => $this->facade ?? null,
            'description' => $this->description ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

