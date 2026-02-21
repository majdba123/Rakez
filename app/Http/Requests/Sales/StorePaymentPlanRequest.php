<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.payment-plan.manage');
    }

    public function rules(): array
    {
        return [
            'installments' => 'required|array|min:1',
            'installments.*.due_date' => 'required|date|after_or_equal:today',
            'installments.*.amount' => 'required|numeric|min:0.01',
            'installments.*.description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'installments.required' => 'At least one installment is required',
            'installments.min' => 'At least one installment is required',
            'installments.*.due_date.required' => 'Due date is required for each installment',
            'installments.*.due_date.after_or_equal' => 'Due date must be today or in the future',
            'installments.*.amount.required' => 'Amount is required for each installment',
            'installments.*.amount.min' => 'Amount must be greater than 0',
        ];
    }
}

