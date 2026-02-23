<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskProjectDetailResource extends JsonResource
{
    /**
     * Transform Contract (with montageDepartment, photographyDepartment, boardsDepartment)
     * into the task project detail structure for Sales task management.
     */
    public function toArray(Request $request): array
    {
        return [
            'contract_id' => $this->id,
            'project_name' => $this->project_name,
            'project_description' => $this->notes ?? $this->project_description ?? '',
            'montage_designs' => [
                'image_url' => $this->montageDepartment?->image_url ?? null,
                'video_url' => $this->montageDepartment?->video_url ?? null,
                'description' => $this->montageDepartment?->description ?? null,
            ],
            'photography' => [
                'image_url' => $this->photographyDepartment?->image_url ?? null,
                'video_url' => $this->photographyDepartment?->video_url ?? null,
                'description' => $this->photographyDepartment?->description ?? null,
            ],
            'boards' => [
                'image_url' => $this->boardsDepartment?->image_url ?? null,
                'description' => $this->boardsDepartment?->description ?? null,
            ],
        ];
    }
}
