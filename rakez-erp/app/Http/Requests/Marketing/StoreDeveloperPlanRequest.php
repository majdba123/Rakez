<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeveloperPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.plans.create');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'marketing_value' => 'nullable|numeric|min:0',
            'marketing_percent' => 'nullable|numeric|min:6|max:10',
            'average_cpm' => 'nullable|numeric|min:0',
            'average_cpc' => 'nullable|numeric|min:0',
            'conversion_rate' => 'nullable|numeric|min:0|max:100',
            'expected_bookings' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
            'platforms' => 'nullable|array',
            'platforms.*.platform_key' => 'nullable|string|max:100',
            'platforms.*.platform_name_ar' => 'nullable|string|max:255',
            'platforms.*.cpm' => 'nullable|numeric|min:0',
            'platforms.*.cpc' => 'nullable|numeric|min:0',
            'platforms.*.views' => 'nullable|integer|min:0',
            'platforms.*.clicks' => 'nullable|integer|min:0',
        ];
    }
}
