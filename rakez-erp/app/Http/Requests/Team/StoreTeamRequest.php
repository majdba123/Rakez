<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'nullable|string|max:32|unique:teams,code',
            'name' => 'required|string|max:255|unique:teams,name',
            'description' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفريق مطلوب',
            'name.max' => 'اسم الفريق يجب ألا يتجاوز 255 حرفاً',
            'name.unique' => 'اسم الفريق مستخدم مسبقاً في قاعدة البيانات',
            'code.max' => 'رمز الفريق يجب ألا يتجاوز 32 حرفاً',
            'code.unique' => 'رمز الفريق مستخدم مسبقاً في قاعدة البيانات',
        ];
    }
}


