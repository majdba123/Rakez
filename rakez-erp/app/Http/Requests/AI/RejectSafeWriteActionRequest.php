<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class RejectSafeWriteActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_key' => ['required', 'string', 'max:120'],
            'proposal_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
