<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission check is done via middleware
    }

    public function rules(): array
    {
        return [
            'module' => ['sometimes', 'required', 'string', 'max:120'],
            'page_key' => ['nullable', 'string', 'max:180'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content_md' => ['sometimes', 'required', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
            'language' => ['sometimes', 'required', 'string', 'max:10', Rule::in(['ar', 'en'])],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

