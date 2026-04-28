<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTeamSalesLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('type', 'sales_leader'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'يجب تحديد المستخدم (user_id).',
            'user_id.exists' => 'المستخدم غير موجود أو نوعه ليس sales_leader (قائد مبيعات).',
        ];
    }
}
