<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistantChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission check is done in controller/middleware
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:6000'],
            'module' => ['nullable', 'string', 'max:120'],
            'page_key' => ['nullable', 'string', 'max:180'],
            'language' => ['nullable', 'string', 'max:10', Rule::in(['ar', 'en'])],
            'conversation_id' => ['nullable', 'integer', 'exists:assistant_conversations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please provide a message.',
            'message.max' => 'Your message is too long. Please keep it under 6000 characters.',
            'conversation_id.exists' => 'The specified conversation does not exist.',
        ];
    }
}

