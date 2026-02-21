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
            // SRS: مهلة التنفيذ (date + time). 'date' accepts both Y-m-d and Y-m-d H:i:s
            'due_at' => 'required|date',
            'assigned_to' => 'required|exists:users,id',
            'status' => 'nullable|string|in:in_progress,completed,could_not_complete',
        ];
    }
}
