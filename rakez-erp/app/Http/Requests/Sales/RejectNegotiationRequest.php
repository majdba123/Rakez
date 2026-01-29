<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class RejectNegotiationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.negotiation.approve');
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A rejection reason is required',
            'reason.max' => 'Rejection reason cannot exceed 1000 characters',
        ];
    }
}

