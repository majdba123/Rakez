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
            'team_ids' => 'required|array|max:1',
            'team_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('teams', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'team_ids.required' => 'يجب إرسال قائمة معرفات الفرق (team_ids).',
            'team_ids.array' => 'قائمة الفرق غير صالحة.',
            'team_ids.max' => 'يُسمح بإرسال فريق واحد فقط داخل team_ids.',
            'team_ids.*.integer' => 'معرف الفريق يجب أن يكون رقماً صحيحاً.',
            'team_ids.*.distinct' => 'لا تكرر نفس معرف الفريق.',
            'team_ids.*.exists' => 'أحد الفرق المحددة غير موجود أو محذوف.',
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
