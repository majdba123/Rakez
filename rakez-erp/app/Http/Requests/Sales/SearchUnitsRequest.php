<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SearchUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.projects.view');
    }

    public function rules(): array
    {
        return [
            'city_id'      => 'nullable|integer|exists:cities,id',
            'district_id'  => 'nullable|integer|exists:districts,id',
            'city'         => 'nullable|string|max:255',
            'district'     => 'nullable|string|max:255',
            'min_area'     => 'nullable|numeric|min:0',
            'max_area'     => 'nullable|numeric|min:0',
            'min_bedrooms' => 'nullable|integer|min:0',
            'max_bedrooms' => 'nullable|integer|min:0',
            'status'       => 'nullable|string|in:available,reserved,sold,pending',
            'min_price'    => 'nullable|numeric|min:0',
            'max_price'    => 'nullable|numeric|min:0',
            'unit_type'    => 'nullable|string|max:255',
            'floor'        => 'nullable|integer',
            'project_id'   => 'nullable|integer|exists:contracts,id',
            'q'            => 'nullable|string|max:255',
            'sort_by'      => 'nullable|string|in:price,area,bedrooms,created_at',
            'sort_dir'     => 'nullable|string|in:asc,desc',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'       => 'حالة الوحدة يجب أن تكون: available, reserved, sold, pending',
            'sort_by.in'      => 'الترتيب يجب أن يكون: price, area, bedrooms, created_at',
            'sort_dir.in'     => 'اتجاه الترتيب يجب أن يكون: asc أو desc',
            'per_page.max'    => 'الحد الأقصى لعدد النتائج بالصفحة هو 100',
            'project_id.exists' => 'المشروع المحدد غير موجود',
        ];
    }
}
