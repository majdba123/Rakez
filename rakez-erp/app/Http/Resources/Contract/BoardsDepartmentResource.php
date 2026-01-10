<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * قسم اللوحات - Boards Department Resource
 */
class BoardsDepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array
     * بيانات قسم اللوحات
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'has_ads' => $this->has_ads,                         // هل يوجد إعلانات

            'processed_by' => $this->when($this->processedByUser, [
                'id' => $this->processedByUser?->id,
                'name' => $this->processedByUser?->name,
                'email' => $this->processedByUser?->email,
                'type' => $this->processedByUser?->type,
            ]),
            'processed_at' => $this->processed_at?->toIso8601String(),
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

