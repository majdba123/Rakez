<?php

namespace App\Http\Requests\AI;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CanonicalToolChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:16000'],
            'conversation_id' => ['nullable', 'string', 'max:128'],
            'session_id' => ['nullable', 'string', 'max:128'], // backward-compat
            'locale' => ['nullable', 'in:ar,en'],
            'input_mode' => ['nullable', 'in:text,voice'],
            'context' => ['nullable', 'array'],
            'context.module' => ['nullable', 'string', 'max:120'],
            'context.entity_type' => ['nullable', 'string', 'max:120'],
            'context.entity_id' => ['nullable'],
            'section' => ['nullable', 'string', 'max:120'], // backward-compat
            'page_context' => ['nullable', 'array'], // backward-compat
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('conversation_id') && $this->filled('session_id')) {
            $this->merge([
                'conversation_id' => (string) $this->input('session_id'),
            ]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'validation_error',
                'message' => 'Validation failed.',
                'details' => $validator->errors(),
            ],
        ], 422));
    }
}
