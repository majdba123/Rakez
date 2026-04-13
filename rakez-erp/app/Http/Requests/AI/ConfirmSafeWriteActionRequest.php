<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmSafeWriteActionRequest extends FormRequest
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
            'proposal_id' => ['nullable', 'integer'],
            'proposal_commit_token' => ['nullable', 'string', 'size:64'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'confirmation_phrase' => ['required', 'string', 'max:255', Rule::in(['confirm_draft_only'])],
        ];
    }
}
