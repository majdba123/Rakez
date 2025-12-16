<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'project_name' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'district' => 'sometimes|string|max:255',
            'units_count' => 'sometimes|integer|min:1',
            'unit_type' => 'nullable|string|max:255',
            'average_unit_price' => 'sometimes|numeric|min:0',

            'total_units_value' => 'sometimes|numeric|min:0',

            'notes' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'project_name.string' => 'اسم المشروع يجب أن يكون نصًا',
            'project_name.max' => 'اسم المشروع لا يجب أن يتجاوز 255 حرف',
            'city.string' => 'المدينة يجب أن تكون نصًا',
            'district.string' => 'الحي يجب أن يكون نصًا',
            'units_count.integer' => 'عدد الوحدات يجب أن يكون رقمًا صحيحًا',
            'units_count.min' => 'عدد الوحدات يجب أن يكون أكبر من صفر',
            'average_unit_price.numeric' => 'متوسط سعر الوحدة يجب أن يكون رقمًا',
        ];
    }
}
