<?php

namespace App\Http\Requests\Contract;

use App\Models\Contract;
use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        if ($this->has('side')) {
            $side = $this->input('side');
            if ($side === '' || $side === null) {
                $this->merge(['side' => null]);
            } elseif (is_string($side)) {
                $this->merge(['side' => strtoupper(trim($side))]);
            }
        }
        if ($this->has('contract_type') && $this->input('contract_type') === '') {
            $this->merge(['contract_type' => null]);
        }

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
            'project_image_url' => 'nullable|string|max:500',
            'developer_name' => 'sometimes|string|max:255',
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'district_id' => ['sometimes', 'required', 'integer', 'exists:districts,id'],
            'side' => ['sometimes', 'nullable', 'string', Rule::in(['N', 'W', 'E', 'S'])],
            'contract_type' => 'sometimes|nullable|string|max:100',
            'developer_requiment' => 'sometimes|string',
            'notes' => 'nullable|string',
            'commission_percent' => 'nullable|numeric|min:0',
            'commission_from' => 'nullable|string|max:255',
            'is_off_plan' => 'sometimes|boolean',
            // Units array validation
            'units' => 'sometimes|array|min:1',
            'units.*.type' => 'required_with:units|string|max:255',
            'units.*.count' => 'required_with:units|integer|min:1',
            'units.*.price' => 'required_with:units|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $contractId = (int) $this->route('id');
            $contract = Contract::find($contractId);
            $cityId = $this->filled('city_id')
                ? (int) $this->input('city_id')
                : (int) ($contract?->city_id ?? 0);
            $districtId = $this->filled('district_id')
                ? (int) $this->input('district_id')
                : (int) ($contract?->district_id ?? 0);

            if ($cityId < 1 || $districtId < 1) {
                return;
            }

            $belongs = District::query()
                ->where('id', $districtId)
                ->where('city_id', $cityId)
                ->exists();

            if (!$belongs) {
                $validator->errors()->add('district_id', 'الحي لا يتبع المدينة المحددة');
            }
        });
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'project_name.string' => 'اسم المشروع يجب أن يكون نصًا',
            'project_name.max' => 'اسم المشروع لا يجب أن يتجاوز 255 حرف',
            'city_id.exists' => 'المدينة غير موجودة',
            'district_id.exists' => 'الحي غير موجود',
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
