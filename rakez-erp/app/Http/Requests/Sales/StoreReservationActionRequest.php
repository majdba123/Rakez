<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    public function rules(): array
    {
        return [
            'action_type' => 'required|in:lead_acquisition,persuasion,closing,جلب,إقناع,إقفال',
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'action_type.required' => 'Action type is required',
            'action_type.in' => 'Invalid action type',
        ];
    }
}
