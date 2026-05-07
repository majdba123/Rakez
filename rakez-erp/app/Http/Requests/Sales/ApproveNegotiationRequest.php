<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class ApproveNegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.negotiation.approve');
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'Manager notes cannot exceed 1000 characters',
        ];
    }
}

