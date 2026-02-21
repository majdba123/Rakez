<?php

namespace App\Http\Requests\ExclusiveProject;

use Illuminate\Foundation\Http\FormRequest;

class StoreExclusiveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exclusive_projects.request');
    }

    public function rules(): array
    {
        $rules = [
            'project_name' => 'required|string|max:255',
            'developer_name' => 'required|string|max:255',
            'developer_contact' => 'required|string|max:50',
            'project_description' => 'nullable|string|max:2000',
            'estimated_units' => 'nullable|integer|min:0',
            'unit_type' => 'nullable|string|max:100',
            'estimated_unit_price' => 'nullable|numeric|min:0',
            'total_value' => 'nullable|numeric|min:0',
            'location_city' => 'required|string|max:100',
            'location_district' => 'nullable|string|max:100',
        ];

        // New payload: multiple unit types per request
        $rules['units'] = 'sometimes|array|min:1';
        $rules['units.*.unit_type'] = 'required_with:units|string|max:100';
        $rules['units.*.count'] = 'required_with:units|integer|min:1';
        $rules['units.*.average_price'] = 'nullable|numeric|min:0';

        return $rules;
    }

    public function messages(): array
    {
        return [
            'project_name.required' => 'Project name is required',
            'developer_name.required' => 'Developer name is required',
            'developer_contact.required' => 'Developer contact is required',
            'location_city.required' => 'Location city is required',
            'estimated_units.integer' => 'Estimated units must be a number',
            'estimated_units.min' => 'Estimated units must be at least 1',
        ];
    }
}
