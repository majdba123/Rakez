<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SendUnitDeveloperPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.projects.view');
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (empty($input['payments']) && !empty($input['payment_plan']) && is_array($input['payment_plan'])) {
            $input['payments'] = $input['payment_plan'];
        }

        if (!empty($input['payments']) && is_array($input['payments'])) {
            $input['payments'] = array_map(function ($payment) {
                if (!is_array($payment)) {
                    return $payment;
                }
                if ((!isset($payment['payment']) || $payment['payment'] === '' || $payment['payment'] === null)
                    && isset($payment['amount'])) {
                    $payment['payment'] = $payment['amount'];
                }
                if (empty($payment['date']) && !empty($payment['due_date'])) {
                    $payment['date'] = $payment['due_date'];
                }

                return $payment;
            }, $input['payments']);
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'payments' => 'required|array|min:1',
            'payments.*' => 'array',
            'payments.*.payment' => 'required|numeric|min:0.01',
            'payments.*.date' => 'nullable|date',
            'message' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required' => 'Payments array is required',
            'payments.min' => 'At least one payment item is required',
            'payments.*.payment.required' => 'Payment amount is required for each payment item',
            'payments.*.payment.min' => 'Payment amount must be greater than 0',
        ];
    }
}
