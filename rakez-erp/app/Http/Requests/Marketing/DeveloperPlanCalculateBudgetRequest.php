<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates {@see \App\Http\Controllers\Marketing\DeveloperMarketingPlanController::calculateBudget} only.
 * Canonical budget preview: POST /marketing/developer-plans/calculate-budget.
 */
class DeveloperPlanCalculateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.plans.create');
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
            'marketing_percent' => 'required|numeric|min:0|max:100',
            'average_cpm' => 'nullable|numeric|min:0',
            'average_cpc' => 'nullable|numeric|min:0',
            /** Explicit SAR override for commission base (audited in pricing_basis.source). */
            'total_unit_price_override' => 'nullable|numeric|min:0',
            /** @deprecated Use total_unit_price_override */
            'unit_price' => 'nullable|numeric|min:0',
        ];
    }
}
