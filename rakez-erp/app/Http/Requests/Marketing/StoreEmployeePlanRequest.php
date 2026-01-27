<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marketing_project_id' => 'required|exists:marketing_projects,id',
            'user_id' => 'required|exists:users,id',
            'commission_value' => 'nullable|numeric',
            'marketing_value' => 'nullable|numeric',
            'platform_distribution' => 'nullable|array',
            'campaign_distribution' => 'nullable|array',
        ];
    }
}
