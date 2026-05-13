<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesProjectRewardSettingPayload;
use App\Models\ProjectRewardSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRewardSettingRequest extends FormRequest
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
            $userKey = $pair['user'];
            $pctKey = $pair['pct'];

            if (
                array_key_exists($userKey, $this->all())
                && array_key_exists($pctKey, $this->all())
                && $this->input($pctKey) === null
            ) {
                $merge[$pctKey] = 0;
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
        $pct = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'];

        return [
            'contract_id' => ['sometimes', 'exists:contracts,id'],
            'calculation_mode' => ['sometimes', Rule::in([
                ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE,
                ProjectRewardSetting::MODE_MANUAL_AMOUNT,
            ])],
            'reward_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'source' => ['sometimes', Rule::in([
                ProjectRewardSetting::SOURCE_DEVELOPER,
                ProjectRewardSetting::SOURCE_COMPANY,
            ])],
            'tax_enabled' => ['sometimes', 'nullable', 'boolean'],
            'vat_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'assigned_bring_percentage' => $pct,
            'assigned_convince_percentage' => $pct,
            'assigned_close_percentage' => $pct,
            'outside_bring_percentage' => $pct,
            'outside_convince_percentage' => $pct,
            'outside_close_percentage' => $pct,
            'ceo_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'ceo_percentage' => $pct,
            'sales_manager_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_manager_percentage' => $pct,
            'sales_leader_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'sales_leader_percentage' => $pct,
            'group_leader_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'group_leader_percentage' => $pct,
            'external_marketer_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'external_marketer_percentage' => $pct,
            'is_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator): void {
            /** @var ProjectRewardSetting|null $existing */
            $existing = $this->route('projectRewardSetting');

            $mode = $this->input('calculation_mode', $existing?->calculation_mode);
            $rewardPercentage = $this->input('reward_percentage', $existing?->reward_percentage);

            if (
                $mode === ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE
                && ($rewardPercentage === null || $rewardPercentage === '')
            ) {
                $validator->errors()->add(
                    'reward_percentage',
                    'The reward_percentage field is required when calculation_mode is percentage_of_sale.'
                );
            }

            $value = function (string $field) use ($existing) {
                if (array_key_exists($field, $this->all())) {
                    return $this->input($field);
                }

                return $existing?->{$field};
            };

            self::validateDistributionTotalsAndManagement($validator, $value);
        });
    }
}
