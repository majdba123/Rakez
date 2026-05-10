<?php

namespace App\Http\Requests\Contract;

use App\Models\SecondPartyData;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecondPartyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (SecondPartyData::fieldNamesRequiredForContractCompletion() as $field) {
            if (!$this->has($field)) {
                continue;
            }
            $merge[$field] = $this->input($field);
        }

        if ($merge !== []) {
            $this->merge(SecondPartyData::normalizeCompletionFieldsInPayload($merge));
        }
    }

    public function rules(): array
    {
        return [
            'real_estate_papers_url' => 'nullable|url|max:500',
            'plans_equipment_docs_url' => 'nullable|url|max:500',
            'project_logo_url' => 'nullable|url|max:500',
            'prices_units_url' => 'nullable|url|max:500',
            'marketing_license_url' => 'nullable|url|max:500',
            'advertiser_section_url' => 'nullable|string|max:50|regex:/^[0-9]+$/',
            'advertiser_section_expiry_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'real_estate_papers_url.url' => 'رابط اوراق العقار يجب أن يكون رابط صحيح',
            'plans_equipment_docs_url.url' => 'رابط مستندات المخططات والتجهيزات يجب أن يكون رابط صحيح',
            'project_logo_url.url' => 'رابط شعار المشروع يجب أن يكون رابط صحيح',
            'prices_units_url.url' => 'رابط الأسعار والوحدات يجب أن يكون رابط صحيح',
            'marketing_license_url.url' => 'رابط رخصة التسويق يجب أن يكون رابط صحيح',
            'advertiser_section_url.regex' => 'رقم قسم المعلن يجب أن يكون أرقام فقط',
            'advertiser_section_url.max' => 'رقم قسم المعلن يجب أن لا يتجاوز 50 رقم',
            'advertiser_section_expiry_date.date' => 'تاريخ انتهاء رقم المعلن غير صحيح',
        ];
    }
}
