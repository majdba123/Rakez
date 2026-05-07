<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSalesLeader();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'schedules' => 'required|array|min:1',
            'schedules.*.user_id' => 'required|exists:users,id',
            'schedules.*.present' => 'required|boolean',
            'schedules.*.start_time' => ['nullable', 'required_if:schedules.*.present,true', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'schedules.*.end_time' => ['nullable', 'required_if:schedules.*.present,true', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('schedules', []) as $i => $entry) {
                if (empty($entry['present']) || !isset($entry['start_time'], $entry['end_time'])) {
                    continue;
                }
                $start = $this->normalizeTime($entry['start_time']);
                $end = $this->normalizeTime($entry['end_time']);
                if ($end <= $start) {
                    $validator->errors()->add("schedules.{$i}.end_time", 'وقت الانتهاء يجب أن يكون بعد وقت البدء');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $schedules = $this->input('schedules', []);
        foreach ($schedules as $i => $entry) {
            if (!empty($entry['start_time']) && is_string($entry['start_time'])) {
                $schedules[$i]['start_time'] = $this->normalizeTime($entry['start_time']);
            }
            if (!empty($entry['end_time']) && is_string($entry['end_time'])) {
                $schedules[$i]['end_time'] = $this->normalizeTime($entry['end_time']);
            }
        }
        $this->merge(['schedules' => $schedules]);
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
            'date.required' => 'Schedule date is required',
            'schedules.required' => 'At least one schedule entry is required',
            'schedules.*.user_id.required' => 'User ID is required for each entry',
            'schedules.*.user_id.exists' => 'User not found',
            'schedules.*.present.required' => 'Presence status is required for each entry',
            'schedules.*.start_time.required_if' => 'Start time is required when member is present',
            'schedules.*.end_time.required_if' => 'End time is required when member is present',
            'schedules.*.end_time.after' => 'End time must be after start time',
        ];
    }
}
