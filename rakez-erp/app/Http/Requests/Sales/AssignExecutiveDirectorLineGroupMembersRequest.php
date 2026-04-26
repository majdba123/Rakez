<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class AssignExecutiveDirectorLineGroupMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'team_group_id' => ['nullable', 'integer', 'exists:team_groups,id'],
            'user_ids' => ['required', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'يجب إرسال قائمة (user_ids) للأعضاء.',
        ];
    }
}
