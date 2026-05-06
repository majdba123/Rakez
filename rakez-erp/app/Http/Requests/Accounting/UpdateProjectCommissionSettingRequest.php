<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesProjectCommissionSettingPayload;
use App\Models\ProjectCommissionSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectCommissionSettingRequest extends FormRequest
{
    use ValidatesProjectCommissionSettingPayload;

    public function authorize(): bool
    {
        return true;
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
            'project_id' => ['sometimes', 'exists:contracts,id'],
            'commission_source' => ['sometimes', 'in:buyer,owner'],
            'commission_percentage' => ['sometimes', 'numeric', 'min:0'],
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
            /** @var ProjectCommissionSetting|null $existing */
            $existing = $this->route('projectCommissionSetting');

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
