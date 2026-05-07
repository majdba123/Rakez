<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSalesLeader();
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'task_name' => 'required|string|max:255',
            'marketer_id' => 'required|exists:users,id',
            'participating_marketers_count' => 'nullable|integer|min:1',
            'design_link' => 'nullable|url|max:500',
            'design_number' => 'nullable|string|max:100',
            'design_description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_id.required' => 'Project is required',
            'task_name.required' => 'Task name is required',
            'marketer_id.required' => 'Marketer is required',
            'design_link.url' => 'Design link must be a valid URL',
        ];
    }
}
