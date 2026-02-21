<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $map = [
            '???' => 'lead_acquisition',
            '?????' => 'persuasion',
            '?????' => 'persuasion',
            '?????' => 'closing',
            '?????' => 'closing',
        ];

        $actionType = $this->input('action_type');
        if (is_string($actionType) && isset($map[$actionType])) {
            $this->merge(['action_type' => $map[$actionType]]);
        }
    }

    public function authorize(): bool
    {
        return true; // Authorization handled in service
    }

    public function rules(): array
    {
        return [
            'action_type' => 'required|in:lead_acquisition,persuasion,closing',
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
