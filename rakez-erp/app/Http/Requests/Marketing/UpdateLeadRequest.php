<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.projects.view');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'contact_info' => 'sometimes|string|max:255',
            'source' => 'nullable|string|max:255',
            'project_id' => 'sometimes|exists:contracts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,lost,converted',
            'notes' => 'nullable|string',
        ];
    }
}
