<?php

namespace App\Http\Requests\ExclusiveProject;

use Illuminate\Foundation\Http\FormRequest;

class ApproveExclusiveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is project management manager
        return $this->user()->isProjectManagementManager() 
            || $this->user()->can('exclusive_projects.approve');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required_without:approve|nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required_without' => 'Rejection reason is required when rejecting',
            'rejection_reason.max' => 'Rejection reason cannot exceed 1000 characters',
        ];
    }
}
