<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

/**
 * قسم اللوحات - Update Boards Department Request
 */
class UpdateBoardsDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'has_ads' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [

            'has_ads.boolean' => 'قيمة الإعلانات يجب أن تكون صحيحة أو خاطئة',
        ];
    }
}

