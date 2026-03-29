<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
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

    public function rules(): array
    {
        $cityId = (int) $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::unique('cities', 'code')->ignore($cityId),
            ],
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
