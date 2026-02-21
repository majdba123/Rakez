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
            'average_cpm' => 'nullable|numeric|min:0',
            'average_cpc' => 'nullable|numeric|min:0',
            'conversion_rate' => 'nullable|numeric|min:0|max:100',
            'expected_bookings' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
