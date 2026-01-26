<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSalesLeader();
    }

    public function rules(): array
    {
        return [
            'marketer_id' => 'required|exists:users,id',
            'contract_id' => 'required|exists:contracts,id',
            'contract_unit_id' => 'nullable|exists:contract_units,id',
            'target_type' => 'required|in:reservation,negotiation,closing',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'leader_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'marketer_id.required' => 'Marketer is required',
            'contract_id.required' => 'Project is required',
            'target_type.required' => 'Target type is required',
            'start_date.required' => 'Start date is required',
            'end_date.required' => 'End date is required',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
