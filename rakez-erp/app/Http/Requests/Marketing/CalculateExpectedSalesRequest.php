<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class CalculateExpectedSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marketing_value' => 'sometimes|numeric|min:0',
            'average_cpm' => 'sometimes|numeric|min:0',
            'average_cpc' => 'sometimes|numeric|min:0',
            'conversion_rate' => 'sometimes|numeric|min:0|max:100',
        ];
    }
}
