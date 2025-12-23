<?php

namespace App\Http\Requests\Contract;

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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and normalize units array if provided
        if ($this->has('units')) {
            $this->normalizeUnits();
        }
    }

    /**
     * Normalize units array data - trim strings and cast types correctly
     */
    protected function normalizeUnits(): void
    {
        $units = $this->input('units', []);

        if (!is_array($units)) {
            return;
        }

        $normalized = [];
        foreach ($units as $unit) {
            if (is_array($unit) && isset($unit['type'], $unit['count'], $unit['price'])) {
                $normalized[] = [
                    'type' => trim((string) $unit['type']),
                    'count' => (int) $unit['count'],
                    'price' => (float) $unit['price'],
                ];
            }
        }

        $this->merge(['units' => $normalized]);
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
            'developer_name' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'district' => 'sometimes|string|max:255',
            'developer_requiment' => 'sometimes|string',
            'notes' => 'nullable|string',
            // Units array validation
            'units' => 'sometimes|array|min:1',
            'units.*.type' => 'required_with:units|string|max:255',
            'units.*.count' => 'required_with:units|integer|min:1',
            'units.*.price' => 'required_with:units|numeric|min:0',
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
            'units.array' => 'الوحدات يجب أن تكون مصفوفة',
            'units.min' => 'يجب إضافة وحدة واحدة على الأقل',
            'units.*.type.required_with' => 'نوع الوحدة مطلوب',
            'units.*.type.string' => 'نوع الوحدة يجب أن يكون نصًا',
            'units.*.count.required_with' => 'عدد الوحدات مطلوب',
            'units.*.count.integer' => 'عدد الوحدات يجب أن يكون رقمًا صحيحًا',
            'units.*.price.required_with' => 'سعر الوحدة مطلوب',
            'units.*.price.numeric' => 'سعر الوحدة يجب أن يكون رقمًا',
        ];
    }
}
