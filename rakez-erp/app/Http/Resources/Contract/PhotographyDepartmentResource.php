<?php

namespace App\Http\Resources\Contract;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * قسم التصوير - Photography Department Resource
 */
class PhotographyDepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array
     * بيانات قسم التصوير
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            // Media URLs - روابط الوسائط
            'image_url' => $this->image_url,                     // رابط الصورة
            'video_url' => $this->video_url,                     // رابط الفيديو
            'description' => $this->description,                 // الوصف


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

