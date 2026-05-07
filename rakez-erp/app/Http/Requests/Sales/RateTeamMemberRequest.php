<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class RateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.team.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.min' => 'التقييم من 1 إلى 5 نجوم',
            'rating.max' => 'التقييم من 1 إلى 5 نجوم',
        ];
    }

    /**
     * At least one of rating or comment must be present.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->filled('rating') && !$this->filled('comment')) {
                $validator->errors()->add('rating', 'يجب إرسال التقييم (1-5) و/أو التعليق عن الموظف.');
            }
        });
    }
}
