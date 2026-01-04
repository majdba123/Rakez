<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_type' => 'nullable|string|max:255',
            'unit_number' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,sold,reserved,available',
            'price' => 'nullable|numeric|min:0',
            'area' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_type.string' => 'نوع الوحدة يجب أن يكون نص',
            'status.in' => 'الحالة يجب أن تكون: pending, sold, reserved, available',
            'price.numeric' => 'السعر يجب أن يكون رقم',
            'price.min' => 'السعر يجب أن يكون 0 أو أكثر',
            'area.string' => 'المساحة يجب أن تكون نص',
        ];
    }
}

