<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class PreviewProjectRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->hasPermissionTo('accounting.project-rewards.view')
            || $this->user()?->hasPermissionTo('accounting.project-reward-settings.manage')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'manual_amount' => ['nullable', 'numeric', 'min:0.01'],
            'reward_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewOptions(): array
    {
        return $this->validated();
    }
}
