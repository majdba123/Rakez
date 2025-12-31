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
            'count' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:pending,sold,reserved,available',
            'price' => 'nullable|numeric|min:0',
            'total_price' => 'nullable|numeric|min:0',
            'area' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'unit_type.string' => 'نوع الوحدة يجب أن يكون نص',
            'count.integer' => 'العدد يجب أن يكون رقم صحيح',
            'count.min' => 'العدد يجب أن يكون 0 أو أكثر',
            'status.in' => 'الحالة يجب أن تكون: pending, sold, reserved, available',
            'price.numeric' => 'السعر يجب أن يكون رقم',
            'price.min' => 'السعر يجب أن يكون 0 أو أكثر',
            'total_price.numeric' => 'السعر الإجمالي يجب أن يكون رقم',
            'area.string' => 'المساحة يجب أن تكون نص',
        ];
    }
}

