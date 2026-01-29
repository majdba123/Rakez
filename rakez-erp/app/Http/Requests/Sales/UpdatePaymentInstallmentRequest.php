<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.payment-plan.manage');
    }

    public function rules(): array
    {
        return [
            'due_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'status' => 'sometimes|in:pending,paid,overdue',
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.date' => 'Invalid due date format',
            'amount.min' => 'Amount must be greater than 0',
            'status.in' => 'Invalid status value',
        ];
    }
}

