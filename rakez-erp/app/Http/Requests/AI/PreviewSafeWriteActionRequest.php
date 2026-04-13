<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class PreviewSafeWriteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', 'max:120'],
            'proposal' => ['required', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'proposal_commit_token' => ['nullable', 'string', 'size:64'],
        ];
    }
}
