<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesExecutiveAvailableUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user
            && $user->can('sales.dashboard.view')
            && $user->canAccessSalesExecutiveAvailableUnitsApi();
    }

    public function rules(): array
    {
        return [
            'contract_id' => 'nullable|integer|exists:contracts,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'district_id' => 'nullable|integer|exists:districts,id',
            'unit_type' => 'nullable|string|max:255',
            'floor' => 'nullable|integer',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'q' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:price,area,unit_number,created_at',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
