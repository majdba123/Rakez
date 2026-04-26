<?php

namespace App\Http\Requests\Team;

use App\Models\TeamGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTeamGroupLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'معرّف الموظف مطلوب',
            'user_id.exists' => 'الموظف غير موجود أو محذوف',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $groupId = (int) $this->route('id');
            $userId = (int) $this->input('user_id');

            $group = TeamGroup::query()->find($groupId);
            if (! $group) {
                $validator->errors()->add('team_group_id', 'المجموعة غير موجودة.');

                return;
            }

            $user = User::query()->find($userId);
            if (! $user) {
                return;
            }

            if ($user->type !== 'sales') {
                $validator->errors()->add('user_id', 'قائد المجموعة يجب أن يكون من نوع مبيعات (sales) فقط.');
            }
            if ($user->is_manager === true) {
                $validator->errors()->add('user_id', 'لا يمكن تعيين مدير فريق كقائد لمجموعة.');
            }
            if ($user->is_executive_director === true) {
                $validator->errors()->add('user_id', 'لا يمكن تعيين مدير تنفيذي كقائد لمجموعة.');
            }
            if ((int) $user->team_id !== (int) $group->team_id) {
                $validator->errors()->add('user_id', 'الموظف يجب أن يكون من نفس فريق المجموعة.');
            }
        });
    }
}
