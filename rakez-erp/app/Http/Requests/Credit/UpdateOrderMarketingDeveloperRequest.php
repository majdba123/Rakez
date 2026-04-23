<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderMarketingDeveloperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (!$this->filled('developer_name') && $this->filled('name')) {
            $merge['developer_name'] = $this->input('name');
        }
        if (!$this->filled('developer_number') && $this->filled('number')) {
            $merge['developer_number'] = $this->input('number');
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'developer_name' => ['required', 'string', 'max:255'],
            'developer_number' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
