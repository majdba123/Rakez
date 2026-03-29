<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        // Clean and normalize units array
        $this->normalizeUnits();
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
            'developer_name' => 'required|string|max:255',
            'developer_number' => 'required|string|max:255',
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(fn ($q) => $q->where('city_id', (int) $this->input('city_id'))),
            ],
            'project_name' => 'required|string|max:255',
            'project_image_url' => 'nullable|string|max:500',
            'developer_requiment' => 'required|string',
            'notes' => 'nullable|string',
            // Commission details (optional, same style as ContractInfo)
            'commission_percent' => 'nullable|numeric|min:0',
            'commission_from' => 'nullable|string|max:255',
            // Units array validation
            'units' => 'required|array|min:1',
            'units.*.type' => 'required|string|max:255',
            'units.*.count' => 'required|integer|min:1',
            'units.*.price' => 'required|numeric|min:0',
        ];
    }


    public function messages(): array
    {
        return [
            'project_name.required' => 'اسم المشروع مطلوب',
            'developer_requiment.required' => 'متطلبات المطور مطلوب',
            'developer_requiment.string' => 'متطلبات المطور يجب أن يكون نصًا',
            'project_name.string' => 'اسم المشروع يجب أن يكون نصًا',
            'project_name.max' => 'اسم المشروع لا يجب أن يتجاوز 255 حرف',
            'developer_number.required' => 'رقم المطور مطلوب',
            'city_id.required' => 'المدينة مطلوبة',
            'city_id.exists' => 'المدينة غير موجودة',
            'district_id.required' => 'الحي مطلوب',
            'district_id.exists' => 'الحي غير موجود أو لا يتبع المدينة المختارة',
            'units.required' => 'يجب إضافة وحدة واحدة على الأقل',
            'units.array' => 'الوحدات يجب أن تكون مصفوفة',
            'units.min' => 'يجب إضافة وحدة واحدة على الأقل',
            'units.*.type.required' => 'نوع الوحدة مطلوب',
            'units.*.type.string' => 'نوع الوحدة يجب أن يكون نصًا',
            'units.*.type.max' => 'نوع الوحدة لا يجب أن يتجاوز 255 حرف',
            'units.*.count.required' => 'عدد الوحدات مطلوب',
            'units.*.count.integer' => 'عدد الوحدات يجب أن يكون رقمًا صحيحًا',
            'units.*.count.min' => 'عدد الوحدات يجب أن يكون أكبر من صفر',
            'units.*.price.required' => 'سعر الوحدة مطلوب',
            'units.*.price.numeric' => 'سعر الوحدة يجب أن يكون رقمًا',
            'units.*.price.min' => 'سعر الوحدة لا يمكن أن يكون سالبًا',
        ];
    }
}
