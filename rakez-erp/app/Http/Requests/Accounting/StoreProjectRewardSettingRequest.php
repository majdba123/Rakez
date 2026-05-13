<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesProjectRewardSettingPayload;
use App\Models\ProjectRewardSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRewardSettingRequest extends FormRequest
{
    use ValidatesProjectRewardSettingPayload;

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('accounting.project-reward-settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (self::managementPairs() as $pair) {
            if (
                $this->filled($pair['user'])
                && $this->input($pair['pct']) === null
                && !array_key_exists($pair['pct'], $this->all())
            ) {
                $merge[$pair['pct']] = 0;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pct = ['nullable', 'numeric', 'min:0', 'max:100'];

        return [
            'contract_id' => ['required', 'exists:contracts,id'],
            'calculation_mode' => ['required', Rule::in([
                ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE,
                ProjectRewardSetting::MODE_MANUAL_AMOUNT,
            ])],
            'reward_percentage' => [
                'nullable',
                'required_if:calculation_mode,'.ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE,
                'numeric',
                'min:0.01',
                'max:100',
            ],
            'source' => ['required', Rule::in([
                ProjectRewardSetting::SOURCE_DEVELOPER,
                ProjectRewardSetting::SOURCE_COMPANY,
            ])],
            'tax_enabled' => ['nullable', 'boolean'],
            'vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'assigned_bring_percentage' => $pct,
            'assigned_convince_percentage' => $pct,
            'assigned_close_percentage' => $pct,
            'outside_bring_percentage' => $pct,
            'outside_convince_percentage' => $pct,
            'outside_close_percentage' => $pct,
            'ceo_user_id' => ['nullable', 'exists:users,id'],
            'ceo_percentage' => $pct,
            'sales_manager_user_id' => ['nullable', 'exists:users,id'],
            'sales_manager_percentage' => $pct,
            'sales_leader_user_id' => ['nullable', 'exists:users,id'],
            'sales_leader_percentage' => $pct,
            'group_leader_user_id' => ['nullable', 'exists:users,id'],
            'group_leader_percentage' => $pct,
            'external_marketer_user_id' => ['nullable', 'exists:users,id'],
            'external_marketer_percentage' => $pct,
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            $value = fn (string $field) => $this->input($field);
            self::validateDistributionTotalsAndManagement($validator, $value);
        });
    }
}
