<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.tasks.view');
    }

    public function rules(): array
    {
        return [
            'task_name' => 'sometimes|string|max:255',
            'marketer_id' => 'sometimes|exists:users,id',
            'due_date' => 'sometimes|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:new,in_progress,completed,cancelled',
        ];
    }
}
