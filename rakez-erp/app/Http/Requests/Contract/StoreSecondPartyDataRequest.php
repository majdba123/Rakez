<?php

namespace App\Http\Requests\Contract;

use Illuminate\Foundation\Http\FormRequest;

class StoreSecondPartyDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // رابط اوراق العقار
            'real_estate_papers_url' => 'nullable|url|max:500',
            // رابط مستندات المخطاطات والتجهيزات
            'plans_equipment_docs_url' => 'nullable|url|max:500',
            // رابط شعار المشروع
            'project_logo_url' => 'nullable|url|max:500',
            // رابط الاسعار والوحرات
            'prices_units_url' => 'nullable|url|max:500',
            // رخصة التسويق
            'marketing_license_url' => 'nullable|url|max:500',
            // قسم معلن
            'advertiser_section_url' => 'nullable|url|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'real_estate_papers_url.url' => 'رابط اوراق العقار يجب أن يكون رابط صحيح',
            'plans_equipment_docs_url.url' => 'رابط مستندات المخططات والتجهيزات يجب أن يكون رابط صحيح',
            'project_logo_url.url' => 'رابط شعار المشروع يجب أن يكون رابط صحيح',
            'prices_units_url.url' => 'رابط الاسعار والوحدات يجب أن يكون رابط صحيح',
            'marketing_license_url.url' => 'رابط رخصة التسويق يجب أن يكون رابط صحيح',
            'advertiser_section_url.url' => 'رابط قسم المعلن يجب أن يكون رابط صحيح',
        ];
    }
}

