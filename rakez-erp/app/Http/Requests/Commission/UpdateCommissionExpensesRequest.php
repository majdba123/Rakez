<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionExpensesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('commissions.update');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'marketing_expenses' => 'required|numeric|min:0',
            'bank_fees' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'marketing_expenses.required' => 'مصاريف التسويق مطلوبة',
            'marketing_expenses.numeric' => 'مصاريف التسويق يجب أن تكون رقماً',
            'marketing_expenses.min' => 'مصاريف التسويق يجب أن تكون صفر أو أكثر',
            'bank_fees.required' => 'رسوم البنك مطلوبة',
            'bank_fees.numeric' => 'رسوم البنك يجب أن تكون رقماً',
            'bank_fees.min' => 'رسوم البنك يجب أن تكون صفر أو أكثر',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'marketing_expenses' => 'مصاريف التسويق',
            'bank_fees' => 'رسوم البنك',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $commission = $this->route('commission');
            
            // Check if commission is in pending status
            if ($commission && $commission->status !== 'pending') {
                $validator->errors()->add('commission', 'لا يمكن تعديل مصاريف عمولة تم اعتمادها');
            }

            // Validate that expenses don't exceed commission amount
            $totalExpenses = $this->input('marketing_expenses', 0) + $this->input('bank_fees', 0);
            if ($commission && $totalExpenses > $commission->total_amount) {
                $validator->errors()->add('expenses', 'إجمالي المصاريف لا يمكن أن يتجاوز مبلغ العمولة');
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
