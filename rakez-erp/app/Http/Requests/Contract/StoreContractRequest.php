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
        $side = $this->input('side');
        if ($side === '' || $side === null) {
            $side = null;
        } elseif (is_string($side)) {
            $side = strtoupper(trim($side));
        }

        $contractType = $this->input('contract_type');
        if ($contractType === '') {
            $contractType = null;
        }

        $this->merge([
            'user_id' => auth()->id(),
            'side' => $side,
            'contract_type' => $contractType,
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
     * Rules for API store and for CSV contract import rows (same shape as {@see \App\Jobs\ProcessContractsCsv::buildContractPayload}).
     *
     * CSV import: `units_json` = JSON array of {type, count, price}. Location: either `city_id`+`district_id`
     * or `city_code`+`district_name` (resolved against DB; import cities/districts first).
     *
     * @param  array<string, mixed>  $data  Must include city_id for district exists rule.
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public static function contractImportRules(array $data): array
    {
        $cityId = (int) ($data['city_id'] ?? 0);

        return [
            'developer_name' => 'required|string|max:255',
            'developer_number' => 'required|string|max:255',
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where(fn ($q) => $q->where('city_id', $cityId)),
            ],
            'side' => ['nullable', 'string', Rule::in(['N', 'W', 'E', 'S'])],
            'contract_type' => 'nullable|string|max:100',
            'project_name' => 'required|string|max:255',
            'project_image_url' => 'nullable|string|max:500',
            'developer_requiment' => 'required|string',
            'notes' => 'nullable|string',
            'commission_percent' => 'nullable|numeric|min:0',
            'commission_from' => 'nullable|string|max:255',
            'units' => 'required|array|min:1',
            'units.*.type' => 'required|string|max:255',
            'units.*.count' => 'required|integer|min:1',
            'units.*.price' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return self::contractImportRules($this->all());
    }

    /**
     * @return array<string, string>
     */
    public static function contractImportMessages(): array
    {
        return [
            'developer_name.required' => 'اسم المطور مطلوب',
            'developer_number.required' => 'رقم المطور مطلوب',
            'project_name.required' => 'اسم المشروع مطلوب',
            'developer_requiment.required' => 'متطلبات المطور مطلوب',
            'developer_requiment.string' => 'متطلبات المطور يجب أن يكون نصًا',
            'project_name.string' => 'اسم المشروع يجب أن يكون نصًا',
            'project_name.max' => 'اسم المشروع لا يجب أن يتجاوز 255 حرف',
            'city_id.required' => 'المدينة مطلوبة: عيّن city_id صالحاً أو املأ city_code مع district_name ليُحلّ الموقع تلقائياً',
            'city_id.exists' => 'رقم المدينة (city_id) غير موجود في النظام. استخدم معرفاً من قائمة المدن الفعلية، أو استورد المدن أولاً، أو استخدم city_code مع district_name بعد التأكد من استيراد المدينة والحي',
            'district_id.required' => 'الحي مطلوب: عيّن district_id صالحاً أو املأ district_name مع city_code الصحيح',
            'district_id.exists' => 'رقم الحي (district_id) غير موجود أو لا ينتمي إلى المدينة المحددة في نفس الصف. راجع أن الحي تابع لنفس city_id، أو استخدم district_name مع city_code المطابق في قاعدة البيانات',
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

    public function messages(): array
    {
        return self::contractImportMessages();
    }
}
