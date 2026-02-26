<?php

namespace App\Http\Resources\Sales;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesAttendanceResource extends JsonResource
{
    private const DAY_NAMES_AR = [
        0 => 'الأحد',
        1 => 'الإثنين',
        2 => 'الثلاثاء',
        3 => 'الأربعاء',
        4 => 'الخميس',
        5 => 'الجمعة',
        6 => 'السبت',
    ];

    public function toArray(Request $request): array
    {
        $date = $this->schedule_date instanceof Carbon
            ? $this->schedule_date
            : Carbon::parse($this->schedule_date);
        $dayNameAr = self::DAY_NAMES_AR[$date->dayOfWeek] ?? '';

        return [
            'schedule_id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name ?? 'N/A',
            'schedule_date' => $date->format('Y-m-d'),
            'day_of_week' => $date->format('l'),
            'day_name_ar' => $dayNameAr,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'project_id' => $this->contract_id,
            'project_name' => $this->contract->project_name ?? 'N/A',
            'project_location' => trim(($this->contract->city ?? '') . ', ' . ($this->contract->district ?? ''), ', '),
        ];
    }
}
