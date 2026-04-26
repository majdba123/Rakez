<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSalesTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $teamId = (int) $this->route('teamId');

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'team_group_id' => [
                'required',
                'integer',
                Rule::exists('team_groups', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'team_group_id.required' => 'يجب اختيار مجموعة داخل الفريق (team_group_id).',
            'team_group_id.exists' => 'المجموعة غير موجودة أو لا تنتمي لهذا الفريق.',
        ];
    }
}
