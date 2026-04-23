<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    /**
     * Same rules as {@see rules()} for CSV import rows (city_id comes from the row).
     */
    public static function rulesForCsvRow(int $cityId): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('districts', 'name')->where(
                    fn ($query) => $query->where('city_id', $cityId)
                ),
            ],
        ];
    }

    public function rules(): array
    {
        return self::rulesForCsvRow((int) $this->input('city_id'));
    }

    public function messages(): array
    {
        return [
            'city_id.required' => 'المدينة مطلوبة',
            'city_id.exists' => 'المدينة غير موجودة',
            'name.required' => 'اسم الحي مطلوب',
            'name.max' => 'اسم الحي يجب ألا يتجاوز 255 حرفاً',
            'name.unique' => 'اسم الحي موجود مسبقاً لهذه المدينة',
        ];
    }
}
