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
            'members' => ['required', 'array', 'max:100'],
            'members.*.user_id' => ['required', 'integer', 'distinct'],
            'members.*.value_target' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'members.required' => 'يجب إرسال قائمة (members) للأعضاء.',
            'members.array' => 'قائمة الأعضاء غير صالحة.',
            'members.*.user_id.required' => 'حقل user_id مطلوب لكل عضو.',
            'members.*.user_id.integer' => 'user_id يجب أن يكون رقماً صحيحاً.',
            'members.*.user_id.distinct' => 'لا يمكن تكرار نفس المستخدم.',
            'members.*.value_target.required' => 'حقل value_target مطلوب لكل عضو.',
            'members.*.value_target.numeric' => 'value_target يجب أن يكون قيمة رقمية.',
            'members.*.value_target.min' => 'value_target لا يمكن أن يكون أقل من صفر.',
        ];
    }
}
