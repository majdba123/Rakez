<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'schedule_id' => $this->id,
            'user_name' => $this->user->name ?? 'N/A',
            'schedule_date' => $this->schedule_date->format('Y-m-d'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'project_name' => $this->contract->project_name ?? 'N/A',
            'project_location' => ($this->contract->city ?? '') . ', ' . ($this->contract->district ?? ''),
        ];
    }
}
