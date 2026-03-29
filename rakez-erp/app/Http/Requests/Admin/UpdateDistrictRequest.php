<?php

namespace App\Http\Requests\Admin;

use App\Models\District;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistrictRequest extends FormRequest
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

    public function rules(): array
    {
        $districtId = (int) $this->route('id');

        return [
            'city_id' => ['sometimes', 'required', 'integer', 'exists:cities,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $districtId = (int) $this->route('id');
            $district = District::find($districtId);
            if (!$district) {
                return;
            }

            $cityId = $this->filled('city_id')
                ? (int) $this->input('city_id')
                : (int) $district->city_id;

            $name = $this->filled('name')
                ? (string) $this->input('name')
                : $district->name;

            if ($name === null || $name === '') {
                return;
            }

            $exists = District::query()
                ->where('city_id', $cityId)
                ->where('name', $name)
                ->where('id', '!=', $districtId)
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'اسم الحي موجود مسبقاً لهذه المدينة');
            }
        });
    }

    public function messages(): array
    {
        return [
            'city_id.exists' => 'المدينة غير موجودة',
            'name.required' => 'اسم الحي مطلوب',
            'name.max' => 'اسم الحي يجب ألا يتجاوز 255 حرفاً',
        ];
    }
}
