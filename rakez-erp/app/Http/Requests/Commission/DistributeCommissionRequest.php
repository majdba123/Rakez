<?php

namespace App\Http\Requests\Commission;

use Illuminate\Foundation\Http\FormRequest;

class DistributeCommissionRequest extends FormRequest
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
            'distributions' => 'required|array|min:1',
            'distributions.*.user_id' => 'required_without:distributions.*.external_name|nullable|exists:users,id',
            'distributions.*.external_name' => 'required_without:distributions.*.user_id|nullable|string|max:255',
            'distributions.*.type' => 'required|in:lead_generation,persuasion,closing,team_leader,sales_manager,project_manager,external_marketer,other',
            'distributions.*.percentage' => 'required|numeric|min:0|max:100',
            'distributions.*.bank_account' => 'nullable|string|max:50',
            'distributions.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom validation messages in Arabic.
     */
    public function messages(): array
    {
        return [
            'distributions.required' => 'يجب إضافة توزيع واحد على الأقل',
            'distributions.array' => 'التوزيعات يجب أن تكون مصفوفة',
            'distributions.min' => 'يجب إضافة توزيع واحد على الأقل',
            'distributions.*.user_id.required_without' => 'معرف الموظف أو الاسم الخارجي مطلوب',
            'distributions.*.user_id.exists' => 'الموظف المحدد غير موجود',
            'distributions.*.external_name.required_without' => 'الاسم الخارجي أو معرف الموظف مطلوب',
            'distributions.*.external_name.max' => 'الاسم الخارجي يجب ألا يتجاوز 255 حرفاً',
            'distributions.*.type.required' => 'نوع التوزيع مطلوب',
            'distributions.*.type.in' => 'نوع التوزيع غير صحيح',
            'distributions.*.percentage.required' => 'نسبة التوزيع مطلوبة',
            'distributions.*.percentage.numeric' => 'نسبة التوزيع يجب أن تكون رقماً',
            'distributions.*.percentage.min' => 'نسبة التوزيع يجب أن تكون صفر أو أكثر',
            'distributions.*.percentage.max' => 'نسبة التوزيع يجب ألا تتجاوز 100%',
            'distributions.*.bank_account.max' => 'رقم الحساب البنكي يجب ألا يتجاوز 50 حرفاً',
            'distributions.*.notes.max' => 'الملاحظات يجب ألا تتجاوز 500 حرف',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $distributions = $this->input('distributions', []);
            
            // Validate total percentage equals 100%
            $totalPercentage = array_sum(array_column($distributions, 'percentage'));
            if (abs($totalPercentage - 100) > 0.01) {
                $validator->errors()->add('distributions', 'مجموع نسب التوزيع يجب أن يساوي 100%');
            }

            // Check for duplicate user_id in distributions
            $userIds = array_filter(array_column($distributions, 'user_id'));
            if (count($userIds) !== count(array_unique($userIds))) {
                $validator->errors()->add('distributions', 'لا يمكن توزيع العمولة على نفس الموظف أكثر من مرة');
            }

            // Validate external marketer has bank account
            foreach ($distributions as $index => $distribution) {
                if (isset($distribution['type']) && $distribution['type'] === 'external_marketer') {
                    if (empty($distribution['bank_account'])) {
                        $validator->errors()->add("distributions.{$index}.bank_account", 'رقم الحساب البنكي مطلوب للمسوق الخارجي');
                    }
                    if (empty($distribution['external_name'])) {
                        $validator->errors()->add("distributions.{$index}.external_name", 'اسم المسوق الخارجي مطلوب');
                    }
                }
            }

            // Check if commission is in pending status
            $commission = $this->route('commission');
            if ($commission && $commission->status !== 'pending') {
                $validator->errors()->add('commission', 'لا يمكن توزيع عمولة تم اعتمادها');
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
