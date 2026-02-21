<?php

namespace App\Http\Requests\Deposit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepositRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deposits.update');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'sometimes|required|numeric|min:1',
            'payment_method' => 'sometimes|required|in:bank_transfer,cash,bank_financing',
            'client_name' => 'sometimes|required|string|max:255',
            'payment_date' => 'sometimes|required|date|before_or_equal:today',
            'commission_source' => 'sometimes|required|in:owner,buyer',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'مبلغ الوديعة مطلوب',
            'amount.numeric' => 'مبلغ الوديعة يجب أن يكون رقماً',
            'amount.min' => 'مبلغ الوديعة يجب أن يكون أكبر من صفر',
            'payment_method.required' => 'طريقة الدفع مطلوبة',
            'payment_method.in' => 'طريقة الدفع يجب أن تكون إما تحويل بنكي، نقداً، أو تمويل بنكي',
            'client_name.required' => 'اسم العميل مطلوب',
            'client_name.max' => 'اسم العميل يجب ألا يتجاوز 255 حرفاً',
            'payment_date.required' => 'تاريخ الدفع مطلوب',
            'payment_date.date' => 'تاريخ الدفع يجب أن يكون تاريخاً صحيحاً',
            'payment_date.before_or_equal' => 'تاريخ الدفع يجب ألا يكون في المستقبل',
            'commission_source.required' => 'مصدر العمولة مطلوب',
            'commission_source.in' => 'مصدر العمولة يجب أن يكون إما مالك أو مشتري',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'مبلغ الوديعة',
            'payment_method' => 'طريقة الدفع',
            'client_name' => 'اسم العميل',
            'payment_date' => 'تاريخ الدفع',
            'commission_source' => 'مصدر العمولة',
            'notes' => 'الملاحظات',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $deposit = $this->route('deposit');
            
            // Check if deposit is in pending status
            if ($deposit && $deposit->status !== 'pending') {
                $validator->errors()->add('deposit', 'لا يمكن تعديل وديعة تم تأكيدها أو استردادها');
            }

            // Prevent changing commission_source if deposit is not pending
            if ($this->has('commission_source') && $deposit) {
                if ($deposit->status !== 'pending') {
                    $validator->errors()->add('commission_source', 'لا يمكن تغيير مصدر العمولة بعد تأكيد الوديعة');
                }
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
