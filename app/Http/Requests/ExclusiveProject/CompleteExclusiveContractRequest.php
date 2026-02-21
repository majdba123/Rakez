<?php

namespace App\Http\Requests\ExclusiveProject;

use Illuminate\Foundation\Http\FormRequest;

class CompleteExclusiveContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exclusive_projects.contract.complete');
    }

    public function rules(): array
    {
        return [
            'units' => 'nullable|array',
            'units.*.type' => 'required|string|max:100',
            'units.*.count' => 'required|integer|min:1',
            'units.*.price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'units.array' => 'Units must be an array',
            'units.*.type.required' => 'Unit type is required',
            'units.*.count.required' => 'Unit count is required',
            'units.*.count.integer' => 'Unit count must be a number',
            'units.*.count.min' => 'Unit count must be at least 1',
            'units.*.price.required' => 'Unit price is required',
            'units.*.price.numeric' => 'Unit price must be a number',
            'units.*.price.min' => 'Unit price cannot be negative',
        ];
    }
}
