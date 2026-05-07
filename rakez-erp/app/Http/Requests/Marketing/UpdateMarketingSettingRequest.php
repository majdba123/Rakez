<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marketing.dashboard.view');
    }

    public function rules(): array
    {
        return [
            'value' => 'required|string',
            'description' => 'nullable|string|max:500',
        ];
    }
}
