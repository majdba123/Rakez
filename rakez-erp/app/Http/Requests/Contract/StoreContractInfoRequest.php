<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreContractInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // nothing special for now
    }

    public function rules(): array
    {
        return [
            'gregorian_date' => 'nullable|date',
            'hijri_date' => 'nullable|string|max:50',
            'contract_city' => 'nullable|string|max:255',
            'agreement_duration_days' => 'nullable|integer|min:0',
            'commission_percent' => 'nullable|numeric|min:0',
            'commission_from' => 'nullable|string|max:255',
            'agency_number' => 'nullable|string|max:255',
            'agency_date' => 'nullable|date',
            'avg_property_value' => 'nullable|numeric|min:0',
            'release_date' => 'nullable|date',
            'second_party_developer_id' => 'nullable|integer|exists:users,id',
            'second_party_name' => 'nullable|string|max:255',
            'second_party_address' => 'nullable|string',
            'second_party_cr_number' => 'nullable|string|max:255',
            'second_party_signatory' => 'nullable|string|max:255',
            'second_party_id_number' => 'nullable|string|max:255',
            'second_party_role' => 'nullable|string|max:255',
            'second_party_phone' => 'nullable|string|max:255',
        ];
    }
}
