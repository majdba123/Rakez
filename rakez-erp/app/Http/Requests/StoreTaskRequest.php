<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'task_name' => 'required|string|max:255',
            'team_id' => 'required|exists:teams,id',
            // SRS: مهلة التنفيذ (date + time), not date-only.
            'due_at' => ['required', 'date', 'regex:/\d{2}:\d{2}/'],
            'assigned_to' => 'required|exists:users,id',
            'status' => 'required|string|in:in_progress,completed,could_not_complete',
        ];
    }

    public function messages(): array
    {
        return [
            'due_at.regex' => 'The due_at field must include both date and time.',
        ];
    }
}
