<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * قسم التصوير - Approve or reject photography department review
 */
class ApprovePhotographyDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('comment') && is_string($this->input('comment'))) {
            $this->merge(['comment' => trim($this->input('comment'))]);
        }
    }

    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'comment' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => $this->has('approved') && $this->boolean('approved') === false),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'approved.required' => 'يجب تحديد ما إذا تم الاعتماد أم الرفض',
            'approved.boolean' => 'قيمة الاعتماد يجب أن تكون صحيحة أو خاطئة',
            'comment.required_if' => 'يجب إدخال سبب الرفض عند عدم الاعتماد',
            'comment.max' => 'تعليق الرفض يجب أن لا يتجاوز 2000 حرف',
        ];
    }
}
