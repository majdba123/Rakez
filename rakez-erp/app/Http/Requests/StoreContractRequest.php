<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'developer_number' => 'required|string|max:255|unique:contracts,developer_number',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'project_name' => 'required|string|max:255',
            'units_count' => 'required|integer|min:1',
            'unit_type' => 'nullable|string|max:255',
            'average_unit_price' => 'required|numeric|min:0',
            'total_units_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'project_name.required' => 'اسم المشروع مطلوب',
            'project_name.string' => 'اسم المشروع يجب أن يكون نصًا',
            'project_name.max' => 'اسم المشروع لا يجب أن يتجاوز 255 حرف',
            'developer_number.required' => 'رقم المطور مطلوب',
            'developer_number.unique' => 'رقم المطور مستخدم بالفعل',
            'city.required' => 'المدينة مطلوبة',
            'district.required' => 'الحي مطلوب',
            'units_count.required' => 'عدد الوحدات مطلوب',
            'units_count.integer' => 'عدد الوحدات يجب أن يكون رقمًا صحيحًا',
            'units_count.min' => 'عدد الوحدات يجب أن يكون أكبر من صفر',
            'average_unit_price.required' => 'متوسط سعر الوحدة مطلوب',
            'average_unit_price.numeric' => 'متوسط سعر الوحدة يجب أن يكون رقمًا',
        ];
    }
}
