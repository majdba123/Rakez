<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.team.manage');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'sometimes|exists:contracts,id',
            'user_id' => 'sometimes|exists:users,id',
            'schedule_date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s|after:start_time',
        ];
    }
}
