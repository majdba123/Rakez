<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => 'nullable|string|max:500',
        ];
    }
}
