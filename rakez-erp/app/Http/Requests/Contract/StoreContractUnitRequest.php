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
            'status' => 'nullable|string|in:pending,sold,reserved,available',
            'price' => 'required|numeric|min:0',
            'area' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_type.required' => 'نوع الوحدة مطلوب',
            'unit_number.required' => 'رقم الوحدة مطلوب',
            'price.required' => 'السعر مطلوب',
            'status.in' => 'الحالة يجب أن تكون: pending, sold, reserved, available',
        ];
    }
}

