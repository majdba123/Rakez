<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.projects.view');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_info' => 'required|string|max:255',
            'source' => 'nullable|string|max:255',
            'project_id' => 'required|exists:contracts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,lost,converted',
            'notes' => 'nullable|string',
        ];
    }
}
