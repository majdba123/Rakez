<?php

namespace App\Http\Requests\Marketing;

use App\Rules\CommonValidationRules;
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
            'name' => CommonValidationRules::name(),
            'contact_info' => CommonValidationRules::name(),
            'source' => 'nullable|string|max:255',
            'project_id' => 'required|exists:contracts,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:new,contacted,qualified,lost,converted',
            'notes' => 'nullable|string',
        ];
    }
}
