<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareAssistantDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'provider' => ['nullable', 'string', Rule::in(['openai', 'anthropic'])],
            'flow' => [
                'nullable',
                'string',
                Rule::in([
                    'create_task_draft',
                    'create_marketing_task_draft',
                    'create_lead_draft',
                    'log_reservation_action_draft',
                    'log_credit_client_contact_draft',
                ]),
            ],
        ];
    }
}
