<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class CalculateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.budgets.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('unit_price') && !$this->has('total_unit_price_override')) {
            $this->merge(['total_unit_price_override' => $this->input('unit_price')]);
        }
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'marketing_percent' => 'nullable|numeric|min:0|max:100',
            'marketing_value' => 'nullable|numeric|min:0',
            'average_cpm' => 'nullable|numeric|min:0',
            'average_cpc' => 'nullable|numeric|min:0',
            'conversion_rate' => 'nullable|numeric|min:0|max:100',
            /** Explicit SAR override for commission base (audited in pricing_basis.source). */
            'total_unit_price_override' => 'nullable|numeric|min:0',
            /** @deprecated Use total_unit_price_override */
            'unit_price' => 'nullable|numeric|min:0',
        ];
    }
}
