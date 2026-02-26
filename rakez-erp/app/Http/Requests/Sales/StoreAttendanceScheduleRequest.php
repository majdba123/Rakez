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
            'start_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/', 'after:start_time'],
        ];
    }

    /**
     * Normalize time to H:i:s for storage (accepts "9:00" or "09:00:00").
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('start_time') && is_string($this->start_time)) {
            $merge['start_time'] = $this->normalizeTime($this->start_time);
        }
        if ($this->has('end_time') && is_string($this->end_time)) {
            $merge['end_time'] = $this->normalizeTime($this->end_time);
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function normalizeTime(string $value): string
    {
        $parts = explode(':', trim($value));
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
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
