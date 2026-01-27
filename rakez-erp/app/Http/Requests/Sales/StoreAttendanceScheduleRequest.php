<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSalesLeader();
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'user_id' => 'required|exists:users,id',
            'schedule_date' => 'required|date',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_id.required' => 'Project is required',
            'user_id.required' => 'User is required',
            'schedule_date.required' => 'Schedule date is required',
            'start_time.required' => 'Start time is required',
            'end_time.required' => 'End time is required',
            'end_time.after' => 'End time must be after start time',
        ];
    }
}
