<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => [
                'sometimes',
                'integer',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:20000',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.exists' => 'الفريق غير موجود أو محذوف',
            'name.max' => 'اسم المجموعة يجب ألا يتجاوز 255 حرفاً',
        ];
    }
}
