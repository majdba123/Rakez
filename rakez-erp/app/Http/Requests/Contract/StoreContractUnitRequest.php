<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_type' => 'required|string|max:255',
            'unit_number' => 'required|string|max:255',
            'count' => 'required|integer|min:1',
            'status' => 'nullable|string|in:pending,sold,reserved,available',
            'price' => 'required|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'area' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_type.required' => 'نوع الوحدة مطلوب',
            'unit_number.required' => 'رقم الوحدة مطلوب',
            'count.required' => 'العدد مطلوب',
            'count.min' => 'العدد يجب أن يكون 1 على الأقل',
            'price.required' => 'السعر مطلوب',
            'status.in' => 'الحالة يجب أن تكون: pending, sold, reserved, available',
        ];
    }
}

