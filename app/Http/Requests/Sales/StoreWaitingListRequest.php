<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.waiting_list.create');
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'required|exists:contracts,id',
            'contract_unit_id' => 'required|exists:contract_units,id',
            'client_name' => 'required|string|max:255',
            'client_mobile' => 'required|string|max:50',
            'client_email' => 'nullable|email|max:255',
            'priority' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'contract_id.required' => 'Contract is required',
            'contract_id.exists' => 'Contract not found',
            'contract_unit_id.required' => 'Unit is required',
            'contract_unit_id.exists' => 'Unit not found',
            'client_name.required' => 'Client name is required',
            'client_mobile.required' => 'Client mobile is required',
            'client_email.email' => 'Invalid email format',
            'priority.integer' => 'Priority must be a number',
            'priority.min' => 'Priority must be at least 1',
            'priority.max' => 'Priority cannot exceed 10',
        ];
    }
}
