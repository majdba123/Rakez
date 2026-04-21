<?php

namespace App\Http\Requests\Credit;

use App\Models\OrderMarketingDeveloper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderMarketingDeveloperStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([OrderMarketingDeveloper::STATUS_APPROVED, OrderMarketingDeveloper::STATUS_REJECTED]),
            ],
        ];
    }
}
