<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->id,
            'contract_id' => $this->contract_id,
            'task_name' => $this->task_name,
            'marketer_name' => $this->marketer->name ?? 'N/A',
            'marketer_phone' => $this->marketer->phone ?? null,
            'participating_marketers_count' => $this->participating_marketers_count,
            'design_link' => $this->design_link,
            'design_number' => $this->design_number,
            'design_description' => $this->design_description,
            'status' => $this->status,
            'status_label_ar' => match ($this->status) {
                'new' => 'جديد',
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'مكتمل',
                default => $this->status,
            },
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
