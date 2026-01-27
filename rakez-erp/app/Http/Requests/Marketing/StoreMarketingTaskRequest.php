<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.tasks.view');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'marketing_project_id' => 'nullable|exists:marketing_projects,id',
            'task_name' => 'required|string|max:255',
            'marketer_id' => 'required|exists:users,id',
            'due_date' => 'required|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'description' => 'nullable|string',
        ];
    }
}
