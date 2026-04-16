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
            'area' => 'nullable|numeric|min:0',
            'floor' => 'nullable|string|max:255',
            'bedrooms' => 'nullable|integer|min:0',
            'bathrooms' => 'nullable|integer|min:0',
            'private_area_m2' => 'nullable|numeric|min:0',
            'street_width' => 'nullable|numeric|min:0',
            'view' => 'nullable|string|max:100',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'diagrames' => 'nullable|string|max:2000',
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

