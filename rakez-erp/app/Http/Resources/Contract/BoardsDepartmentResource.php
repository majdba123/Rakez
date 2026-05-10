<?php

namespace App\Http\Resources\Contract;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardsDepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'has_ads' => $this->has_ads,
            'board_images' => $this->whenLoaded('contract', function () {
                /** @var Collection<int, \App\Models\ProjectMedia> $media */
                $media = $this->contract?->projectMedia ?? collect();

                return ProjectMediaResource::collection(
                    $media->where('department', 'boards')->values()
                );
            }),
            'processed_by' => $this->when($this->processedByUser, [
                'id' => $this->processedByUser?->id,
                'name' => $this->processedByUser?->name,
                'email' => $this->processedByUser?->email,
                'type' => $this->processedByUser?->type,
            ]),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
