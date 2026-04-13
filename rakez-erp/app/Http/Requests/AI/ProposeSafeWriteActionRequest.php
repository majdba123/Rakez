<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class ProposeSafeWriteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}
