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
            'team_groups' => 'required|array|min:1|max:100',
            'team_groups.*.team_group_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('team_groups', 'id')->where('team_id', $teamId),
            ],
            'team_groups.*.value_target' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'team_groups.required' => 'يجب إرسال قائمة المجموعات (team_groups).',
            'team_groups.array' => 'قائمة المجموعات غير صالحة.',
            'team_groups.min' => 'يجب إرسال مجموعة واحدة على الأقل داخل team_groups.',
            'team_groups.*.team_group_id.required' => 'حقل team_group_id مطلوب لكل مجموعة.',
            'team_groups.*.team_group_id.integer' => 'معرف المجموعة يجب أن يكون رقماً.',
            'team_groups.*.team_group_id.distinct' => 'لا تكرر نفس معرف المجموعة.',
            'team_groups.*.team_group_id.exists' => 'أحد المجموعات ليس تابعاً لفريقك أو غير موجود.',
            'team_groups.*.value_target.required' => 'حقل value_target مطلوب لكل مجموعة.',
            'team_groups.*.value_target.numeric' => 'value_target يجب أن يكون رقمياً.',
            'team_groups.*.value_target.min' => 'value_target لا يمكن أن يكون أقل من صفر.',
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
