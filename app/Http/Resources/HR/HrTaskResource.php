<?php

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_name' => $this->task_name,
            'team_id' => $this->team_id,
            'team_name' => $this->team?->name,
            'due_at' => $this->due_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->assignee?->name,
            'status' => $this->status,
            'status_label_ar' => match ($this->status) {
                'in_progress' => 'قيد التنفيذ',
                'completed' => 'تم التنفيذ',
                'could_not_complete' => 'تعذر التنفيذ',
                default => $this->status,
            },
            'cannot_complete_reason' => $this->cannot_complete_reason,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
