<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignExecutiveDirectorLineTeamGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return (bool) ($u && $u->isSalesLeader() && $u->team_id);
    }

    public function rules(): array
    {
        $teamId = (int) ($this->user()->team_id ?? 0);

        return [
            'team_group_ids' => 'required|array|max:1',
            'team_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('team_groups', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'team_group_ids.required' => 'يجب إرسال قائمة معرفات المجموعات (team_group_ids).',
            'team_group_ids.array' => 'قائمة المجموعات غير صالحة.',
            'team_group_ids.max' => 'يُسمح بإرسال مجموعة واحدة فقط داخل team_group_ids.',
            'team_group_ids.*.integer' => 'معرف المجموعة يجب أن يكون رقماً.',
            'team_group_ids.*.distinct' => 'لا تكرر نفس معرف المجموعة.',
            'team_group_ids.*.exists' => 'أحد المجموعات ليس تابعاً لفريقك أو غير موجود.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'تأكد أن جميع المجموعات تابعة لفريقك.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
