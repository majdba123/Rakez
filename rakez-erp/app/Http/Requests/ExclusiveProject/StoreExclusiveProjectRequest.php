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
        return [
            'project_name' => 'required|string|max:255',
            'developer_name' => 'required|string|max:255',
            'developer_contact' => 'required|string|max:50',
            'project_description' => 'nullable|string|max:2000',
            'estimated_units' => 'nullable|integer|min:1',
            'location_city' => 'required|string|max:100',
            'location_district' => 'nullable|string|max:100',
        ];
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
