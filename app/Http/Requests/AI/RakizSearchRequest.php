<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class RakizSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:500'],
            'filters' => ['nullable', 'array'],
        ];
    }
}
