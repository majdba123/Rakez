<?php

namespace App\Http\Requests\Credit;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrderMarketingDeveloperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'integer', 'min:1'],
            'developer_name' => ['sometimes', 'string', 'max:255'],
            'developer_number' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'location' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:32'],
            'created_by' => ['sometimes', 'integer', 'min:1'],
            'updated_by' => ['sometimes', 'integer', 'min:1'],
            'processed_by' => ['sometimes', 'integer', 'min:1'],
            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date'],
            'updated_from' => ['sometimes', 'date'],
            'updated_to' => ['sometimes', 'date'],
        ];
    }
}
