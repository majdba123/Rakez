<?php

namespace App\Http\Requests\Accounting\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesProjectCommissionSettingPayload
{
    /**
     * @return array<int, string>
     */
    public static function distributionPercentageAttributeNames(): array
    {
        return [
            'assigned_bring_percentage',
            'assigned_convince_percentage',
            'assigned_close_percentage',
            'outside_bring_percentage',
            'outside_convince_percentage',
            'outside_close_percentage',
            'ceo_percentage',
            'sales_manager_percentage',
            'sales_leader_percentage',
            'group_leader_percentage',
            'external_marketer_percentage',
        ];
    }

    /**
     * @return array<int, array{pct: string, user: string}>
     */
    public static function managementPairs(): array
    {
        return [
            ['pct' => 'ceo_percentage', 'user' => 'ceo_user_id'],
            ['pct' => 'sales_manager_percentage', 'user' => 'sales_manager_user_id'],
            ['pct' => 'sales_leader_percentage', 'user' => 'sales_leader_user_id'],
            ['pct' => 'group_leader_percentage', 'user' => 'group_leader_user_id'],
            ['pct' => 'external_marketer_percentage', 'user' => 'external_marketer_user_id'],
        ];
    }

    /**
     * @param  callable(string): mixed  $value
     */
    protected static function validateDistributionTotalsAndManagement(Validator $validator, callable $value): void
    {
        $sum = 0.0;
        foreach (self::distributionPercentageAttributeNames() as $field) {
            $sum += round((float) ($value($field) ?? 0), 4);
        }
        if ($sum > 100.0001) {
            $validator->errors()->add(
                'distribution_total',
                'The sum of all distribution percentages must not exceed 100.'
            );
        }

        foreach (self::managementPairs() as $pair) {
            $pct = round((float) ($value($pair['pct']) ?? 0), 4);
            $uid = $value($pair['user']);
            if ($pct > 0 && ($uid === null || $uid === '')) {
                $validator->errors()->add(
                    $pair['user'],
                    "The {$pair['user']} field is required when {$pair['pct']} is greater than 0."
                );
            }
        }
    }
}
