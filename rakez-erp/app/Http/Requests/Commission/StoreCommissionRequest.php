<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('commissions.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contract_unit_id' => 'required|exists:contract_units,id',
            'sales_reservation_id' => 'required|exists:sales_reservations,id',
            'final_selling_price' => 'required|numeric|min:1',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'commission_source' => 'required|in:owner,buyer',
            'team_responsible' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'contract_unit_id.required' => 'معرف الوحدة مطلوب',
            'contract_unit_id.exists' => 'الوحدة المحددة غير موجودة',
            'sales_reservation_id.required' => 'معرف الحجز مطلوب',
            'sales_reservation_id.exists' => 'الحجز المحدد غير موجود',
            'final_selling_price.required' => 'سعر البيع النهائي مطلوب',
            'final_selling_price.numeric' => 'سعر البيع النهائي يجب أن يكون رقماً',
            'final_selling_price.min' => 'سعر البيع النهائي يجب أن يكون أكبر من صفر',
            'commission_percentage.required' => 'نسبة العمولة مطلوبة',
            'commission_percentage.numeric' => 'نسبة العمولة يجب أن تكون رقماً',
            'commission_percentage.min' => 'نسبة العمولة يجب أن تكون صفر أو أكثر',
            'commission_percentage.max' => 'نسبة العمولة يجب ألا تتجاوز 100%',
            'commission_source.required' => 'مصدر العمولة مطلوب',
            'commission_source.in' => 'مصدر العمولة يجب أن يكون إما مالك أو مشتري',
            'team_responsible.max' => 'اسم الفريق المسؤول يجب ألا يتجاوز 255 حرفاً',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'contract_unit_id' => 'معرف الوحدة',
            'sales_reservation_id' => 'معرف الحجز',
            'final_selling_price' => 'سعر البيع النهائي',
            'commission_percentage' => 'نسبة العمولة',
            'commission_source' => 'مصدر العمولة',
            'team_responsible' => 'الفريق المسؤول',
        ];
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
