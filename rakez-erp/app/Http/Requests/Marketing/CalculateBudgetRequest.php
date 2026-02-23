<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class CalculateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.budgets.manage');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'marketing_value' => 'nullable|numeric|min:0',
            'average_cpm' => 'nullable|numeric|min:0',
            'average_cpc' => 'nullable|numeric|min:0',
            'conversion_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
