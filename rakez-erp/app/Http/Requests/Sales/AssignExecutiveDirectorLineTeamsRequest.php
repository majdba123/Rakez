<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignExecutiveDirectorLineTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();

        return $u && ($u->isAdmin() || $u->hasRole('admin') || $u->isSalesTeamManager());
    }

    public function rules(): array
    {
        return [
            'teams' => 'required|array|min:1|max:100',
            'teams.*.team_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
            'teams.*.value_target' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'teams.required' => 'يجب إرسال قائمة الفرق (teams).',
            'teams.array' => 'قائمة الفرق غير صالحة.',
            'teams.min' => 'يجب إرسال فريق واحد على الأقل داخل teams.',
            'teams.*.team_id.required' => 'حقل team_id مطلوب لكل فريق.',
            'teams.*.team_id.integer' => 'معرف الفريق يجب أن يكون رقماً صحيحاً.',
            'teams.*.team_id.distinct' => 'لا تكرر نفس معرف الفريق.',
            'teams.*.team_id.exists' => 'أحد الفرق المحددة غير موجود أو محذوف.',
            'teams.*.value_target.required' => 'حقل value_target مطلوب لكل فريق.',
            'teams.*.value_target.numeric' => 'value_target يجب أن يكون رقمياً.',
            'teams.*.value_target.min' => 'value_target لا يمكن أن يكون أقل من صفر.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator, response()->json([
            'success' => false,
            'message' => 'بيانات الفرق غير صالحة: تحقق من معرفات الفرق الموجودة وغير المحذوفة.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
