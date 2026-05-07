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
            'participating_marketers_count' => 'nullable|integer|min:1',
            'design_link' => 'nullable|string|max:500',
            'design_number' => 'nullable|string|max:100',
            'design_description' => 'nullable|string',
            'status' => 'nullable|string|in:new,in_progress,completed',
        ];
    }
}
