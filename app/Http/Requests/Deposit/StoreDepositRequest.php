<?php

namespace App\Http\Requests\Deposit;

use App\Rules\CommonValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('deposits.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'sales_reservation_id' => 'required|exists:sales_reservations,id',
            'contract_id' => 'required|exists:contracts,id',
            'contract_unit_id' => 'required|exists:contract_units,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:bank_transfer,cash,bank_financing',
            'client_name' => CommonValidationRules::name(),
            'payment_date' => 'required|date|before_or_equal:today',
            'commission_source' => 'required|in:owner,buyer',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'sales_reservation_id.required' => 'معرف الحجز مطلوب',
            'sales_reservation_id.exists' => 'الحجز المحدد غير موجود',
            'contract_id.required' => 'معرف المشروع مطلوب',
            'contract_id.exists' => 'المشروع المحدد غير موجود',
            'contract_unit_id.required' => 'معرف الوحدة مطلوب',
            'contract_unit_id.exists' => 'الوحدة المحددة غير موجودة',
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
            'sales_reservation_id' => 'معرف الحجز',
            'contract_id' => 'معرف المشروع',
            'contract_unit_id' => 'معرف الوحدة',
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
            // Check if unit belongs to contract
            $unitId = $this->input('contract_unit_id');
            $contractId = $this->input('contract_id');

            if ($unitId && $contractId) {
                $unit = \App\Models\ContractUnit::with('secondPartyData')->find($unitId);
                if ($unit && $unit->secondPartyData && $unit->secondPartyData->contract_id != $contractId) {
                    $validator->errors()->add('contract_unit_id', 'الوحدة المحددة لا تنتمي إلى هذا المشروع');
                }
            }

            // Check if reservation belongs to unit
            $reservationId = $this->input('sales_reservation_id');
            if ($reservationId && $unitId) {
                $reservation = \App\Models\SalesReservation::find($reservationId);
                if ($reservation && $reservation->contract_unit_id != $unitId) {
                    $validator->errors()->add('sales_reservation_id', 'الحجز المحدد لا ينتمي إلى هذه الوحدة');
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
