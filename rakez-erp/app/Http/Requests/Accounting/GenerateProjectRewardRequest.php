<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProjectRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('accounting.project-rewards.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'manual_amount' => ['nullable', 'numeric', 'min:0.01'],
            'reward_percentage' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generationOptions(): array
    {
        return $this->validated();
    }
}
