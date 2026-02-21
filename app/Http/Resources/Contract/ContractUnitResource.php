<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractUnitResource extends JsonResource
{
    /** Status to Arabic label for UI */
    private const STATUS_LABELS_AR = [
        'available' => 'متاحة',
        'pending' => 'قيد الانتظار',
        'reserved' => 'محجوزة',
        'sold' => 'مباعة',
    ];

    /**
     * Transform the resource into an array (contract unit details from CSV)
     * وحدات العقد
     */
    public function toArray(Request $request): array
    {
        $unitNumber = $this->unit_number ?? (string) $this->id;
        $unitCode = str_starts_with($unitNumber, '#') ? $unitNumber : '#' . $unitNumber;

        return [
            'id' => $this->id,
            'second_party_data_id' => $this->second_party_data_id,
            'contract_id' => $this->secondPartyData?->contract_id,
            'unit_type' => $this->unit_type,
            'unit_number' => $this->unit_number,
            'unit_code' => $unitCode,
            'status' => $this->status,
            'status_label_ar' => self::STATUS_LABELS_AR[$this->status] ?? $this->status,
            'price' => (float) $this->price,
            'price_formatted' => number_format((float) $this->price) . ' ريال',
            'area' => $this->area,
            'bedrooms' => $this->bedrooms,
            'floor' => $this->floor,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

