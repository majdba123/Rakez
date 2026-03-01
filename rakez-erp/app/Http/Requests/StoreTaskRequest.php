<?php

namespace App\Http\Requests;

use App\Models\User;
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
            'section' => 'required|string',
            'team_id' => 'nullable|exists:teams,id',
            // SRS: مهلة التنفيذ (date + time), not date-only.
            'due_at' => ['required', 'date', 'regex:/\d{2}:\d{2}/'],
            'assigned_to' => 'required|exists:users,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->assigned_to || ! $this->section) {
                return;
            }

            $assignee = User::find($this->assigned_to);
            if (! $assignee) {
                return;
            }

            if ($assignee->type === null) {
                $validator->errors()->add('assigned_to', 'Selected user does not have a section/type.');
                return;
            }

            if ($assignee->type !== $this->section) {
                $validator->errors()->add('assigned_to', 'Selected user does not belong to the chosen section.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'due_at.regex' => 'The due_at field must include both date and time.',
        ];
    }
}
