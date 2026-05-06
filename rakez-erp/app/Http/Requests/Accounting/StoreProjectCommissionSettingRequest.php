<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesProjectCommissionSettingPayload;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectCommissionSettingRequest extends FormRequest
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
            'project_id' => ['required', 'exists:contracts,id'],
            'commission_source' => ['required', 'in:buyer,owner'],
            'commission_percentage' => ['required', 'numeric', 'min:0'],
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
