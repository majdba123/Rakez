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
            'location_url' => 'nullable|string|url|max:500',
            'agreement_duration_days' => 'nullable|integer|min:0',
            'agency_number' => 'nullable|string|max:255',
            'agency_date' => 'nullable|date',
            'avg_property_value' => 'nullable|numeric|min:0',
            'release_date' => 'nullable|date',
            'second_party_name' => 'nullable|string|max:255',
            'second_party_address' => 'nullable|string',
            'second_party_cr_number' => 'nullable|string|max:255',
            'second_party_signatory' => 'nullable|string|max:255',
            'second_party_id_number' => 'nullable|string|max:255',
            'second_party_role' => 'nullable|string|max:255',
            'second_party_phone' => 'nullable|string|max:255',
            'second_party_email' => 'nullable|string|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'gregorian_date.date' => 'يجب أن يكون تاريخ العقد الميلادي صالحاً',
            'hijri_date.string' => 'يجب أن يكون تاريخ العقد الهجري نصاً',
            'location_url.url' => 'رابط الموقع يجب أن يكون عنوان URL صالحاً',
            'second_party_email.email' => 'البريد الإلكتروني للطرف الثاني غير صالح',
        ];
    }


}
