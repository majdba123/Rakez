<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCityRequest extends FormRequest
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
        if ($this->has('code')) {
            $this->merge(['code' => trim((string) $this->input('code'))]);
        }
    }

    /**
     * Row shape for cities+districts CSV (no unique on code — re-import may reference existing cities).
     */
    public static function citiesDistrictsCsvRowRules(): array
    {
        return [
            'city_name' => ['required', 'string', 'max:255'],
            'city_code' => ['required', 'string', 'max:64'],
            'district_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function citiesDistrictsCsvRowMessages(): array
    {
        return [
            'city_name.required' => 'اسم المدينة مطلوب',
            'city_name.max' => 'اسم المدينة يجب ألا يتجاوز 255 حرفاً',
            'city_code.required' => 'رمز المدينة مطلوب',
            'city_code.max' => 'رمز المدينة يجب ألا يتجاوز 64 حرفاً',
            'district_name.max' => 'اسم الحي يجب ألا يتجاوز 255 حرفاً',
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'unique:cities,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المدينة مطلوب',
            'name.max' => 'اسم المدينة يجب ألا يتجاوز 255 حرفاً',
            'code.required' => 'رمز المدينة مطلوب',
            'code.max' => 'رمز المدينة يجب ألا يتجاوز 64 حرفاً',
            'code.unique' => 'رمز المدينة مستخدم مسبقاً',
        ];
    }
}
