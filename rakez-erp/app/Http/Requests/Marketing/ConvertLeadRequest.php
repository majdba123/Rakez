<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class ConvertLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.projects.view');
    }

    public function rules(): array
    {
        return [
            // Optional fields for conversion tracking
            'reservation_id' => 'nullable|exists:sales_reservations,id',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
