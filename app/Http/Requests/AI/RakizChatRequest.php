<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class RakizChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:8000'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'page_context' => ['nullable', 'array'],
            'page_context.route' => ['nullable', 'string', 'max:255'],
            'page_context.entity_id' => ['nullable', 'integer'],
            'page_context.entity_type' => ['nullable', 'string', 'max:64'],
            'page_context.filters' => ['nullable', 'array'],
        ];
    }
}
