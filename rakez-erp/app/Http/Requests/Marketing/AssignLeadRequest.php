<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class AssignLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.projects.view');
    }

    public function rules(): array
    {
        return [
            'assigned_to' => 'required|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_to.required' => 'The assigned to field is required.',
            'assigned_to.exists' => 'The selected user does not exist.',
        ];
    }
}
