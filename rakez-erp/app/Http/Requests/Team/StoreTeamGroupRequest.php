<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'معرّف الفريق مطلوب',
            'team_id.exists' => 'الفريق غير موجود أو محذوف',
            'name.required' => 'اسم المجموعة مطلوب',
            'name.max' => 'اسم المجموعة يجب ألا يتجاوز 255 حرفاً',
        ];
    }
}
