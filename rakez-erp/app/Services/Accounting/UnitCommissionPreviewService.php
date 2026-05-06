<?php

namespace App\Services\Accounting;

use App\Models\ProjectCommissionSetting;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UnitCommissionPreviewService
{
    public function __construct(
        private ProjectCommissionCalculator $calculator,
        private UnitPriceResolver $unitPriceResolver,
    ) {}

    /**
     * @param  array{override_unit_price?: float|null}  $options
     * @return array<string, mixed>
     */
    public function preview(SalesReservation $reservation, array $options = []): array
    {
        $reservation->loadMissing([
            'contract.teams',
            'contractUnit',
            'commission',
            'participantRecords.user.team',
        ]);

        $contract = $reservation->contract;
        if (!$contract) {
            throw ValidationException::withMessages(['reservation' => 'Reservation has no contract.']);
        }

        $setting = ProjectCommissionSetting::query()
            ->where('project_id', $contract->id)
            ->where('is_active', true)
            ->first();

        if (!$setting instanceof ProjectCommissionSetting) {
            throw ValidationException::withMessages([
                'project_commission_setting' => 'No active project commission setting for this project.',
            ]);
        }

        $setting->loadMissing([
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ]);

        $override = $options['override_unit_price'] ?? null;

        $unitPrice = $override !== null && (float) $override > 0
            ? round((float) $override, 2)
            : $this->unitPriceResolver->resolve($reservation);

        $commissionBreakdown = $this->calculator->calculate(
            (string) $setting->commission_source,
            $unitPrice,
            (float) $setting->commission_percentage,
        );

        $unitCommissionAmount = $commissionBreakdown['commission_amount'];

        $constructed = $this->buildParticipantAndManagementDistributions(
            $reservation,
            $setting,
            $unitCommissionAmount,
        );

        $totalDistributed = round(array_sum(array_column($constructed['distributions'], 'amount')), 2);
        $unresolvedTotal = round(array_sum(array_column($constructed['unresolved'], 'amount')), 2);
        $remaining = round($unitCommissionAmount - $totalDistributed - $unresolvedTotal, 2);
        if ($remaining < 0 && $remaining > -0.02) {
            $remaining = 0;
        }

        return [
            'reservation_id' => $reservation->id,
            'project_id' => (int) $contract->id,
            'unit_price' => $unitPrice,
            'commission_source' => $commissionBreakdown['source'],
            'commission_percentage' => (float) $commissionBreakdown['commission_percentage'],
            'formula_key' => $commissionBreakdown['formula_key'],
            'unit_commission_amount' => $unitCommissionAmount,
            'distributions' => $constructed['distributions'],
            'unresolved' => $constructed['unresolved'],
            'total_distributed' => $totalDistributed,
            'remaining_amount' => max(0, $remaining),
        ];
    }

    /**
     * @return array{distributions: list<array<string, mixed>>, unresolved: list<array<string, mixed>>}
     */
    protected function buildParticipantAndManagementDistributions(
        SalesReservation $reservation,
        ProjectCommissionSetting $setting,
        float $unitCommissionAmount,
    ): array {
        $distributions = [];
        $unresolved = [];

        $projectTeamIds = $reservation->contract->teams->pluck('id')->map(fn ($id) => (int) $id)->all();

        /**
         * @var array<string, list<array{user: User, weight: float}>> $bucketMembers key = "{$scope}|{$type}"
         */
        $bucketMembers = [];

        foreach ($reservation->participantRecords as $record) {
            $user = $record->user;
            if (!$user instanceof User) {
                continue;
            }

            $weight = (float) ($record->weight ?? 1);
            if ($weight <= 0) {
                $weight = 1.0;
            }

            $teamId = $user->team_id !== null ? (int) $user->team_id : null;
            $isAssignedProjectTeam = $teamId !== null && in_array($teamId, $projectTeamIds, true);
            $scope = $isAssignedProjectTeam ? 'assigned_project_team' : 'outside_project_team';

            $flags = [];
            if ($record->did_bring) {
                $flags[] = 'bring';
            }
            if ($record->did_convince) {
                $flags[] = 'convince';
            }
            if ($record->did_close) {
                $flags[] = 'close';
            }

            foreach ($flags as $type) {
                $key = $scope.'|'.$type;
                if (!isset($bucketMembers[$key])) {
                    $bucketMembers[$key] = [];
                }
                $bucketMembers[$key][] = ['user' => $user, 'weight' => $weight];
            }
        }

        $bucketDefinitions = $this->bucketDefinitions($setting);

        foreach ($bucketDefinitions as $def) {
            $key = $def['scope'].'|'.$def['type'];
            $members = $bucketMembers[$key] ?? [];
            $pool = $this->poolFromSettingPercentage($unitCommissionAmount, (float) $def['setting_percentage']);

            if ($pool <= 0) {
                continue;
            }

            if ($members === []) {
                $unresolved[] = [
                    'source_scope' => $def['scope'],
                    'source_type' => $def['type'],
                    'percentage' => $this->shareOfUnitCommission($pool, $unitCommissionAmount),
                    'amount' => $pool,
                    'reason' => 'no_matching_participants',
                ];

                continue;
            }

            $allocations = $this->splitPoolByWeight($pool, $members);

            foreach ($allocations as $row) {
                $distributions[] = [
                    'user_id' => $row['user']->id,
                    'user' => [
                        'id' => $row['user']->id,
                        'name' => $row['user']->name,
                    ],
                    'source_scope' => $def['scope'],
                    'source_type' => $def['type'],
                    'percentage' => $this->shareOfUnitCommission($row['amount'], $unitCommissionAmount),
                    'amount' => $row['amount'],
                ];
            }
        }

        foreach ($this->managementDefinitions($setting) as $row) {
            $pct = (float) $row['percentage'];
            if ($pct <= 0 || !$row['user'] instanceof User) {
                continue;
            }

            $amount = round($unitCommissionAmount * $pct / 100, 2);

            $distributions[] = [
                'user_id' => $row['user']->id,
                'user' => [
                    'id' => $row['user']->id,
                    'name' => $row['user']->name,
                ],
                'source_scope' => 'management',
                'source_type' => $row['type'],
                'percentage' => $this->shareOfUnitCommission($amount, $unitCommissionAmount),
                'amount' => $amount,
            ];
        }

        return compact('distributions', 'unresolved');
    }

    /**
     * @return list<array{scope: string, type: string, setting_percentage: float}>
     */
    protected function bucketDefinitions(ProjectCommissionSetting $setting): array
    {
        return [
            ['scope' => 'assigned_project_team', 'type' => 'bring', 'setting_percentage' => (float) $setting->assigned_bring_percentage],
            ['scope' => 'assigned_project_team', 'type' => 'convince', 'setting_percentage' => (float) $setting->assigned_convince_percentage],
            ['scope' => 'assigned_project_team', 'type' => 'close', 'setting_percentage' => (float) $setting->assigned_close_percentage],
            ['scope' => 'outside_project_team', 'type' => 'bring', 'setting_percentage' => (float) $setting->outside_bring_percentage],
            ['scope' => 'outside_project_team', 'type' => 'convince', 'setting_percentage' => (float) $setting->outside_convince_percentage],
            ['scope' => 'outside_project_team', 'type' => 'close', 'setting_percentage' => (float) $setting->outside_close_percentage],
        ];
    }

    /**
     * @return list<array{type: string, percentage: float, user?: User|null}>
     */
    protected function managementDefinitions(ProjectCommissionSetting $setting): array
    {
        return [
            ['type' => 'ceo', 'percentage' => (float) $setting->ceo_percentage, 'user' => $setting->ceoUser],
            ['type' => 'sales_manager', 'percentage' => (float) $setting->sales_manager_percentage, 'user' => $setting->salesManagerUser],
            ['type' => 'sales_leader', 'percentage' => (float) $setting->sales_leader_percentage, 'user' => $setting->salesLeaderUser],
            ['type' => 'group_leader', 'percentage' => (float) $setting->group_leader_percentage, 'user' => $setting->groupLeaderUser],
            ['type' => 'external_marketer', 'percentage' => (float) $setting->external_marketer_percentage, 'user' => $setting->externalMarketerUser],
        ];
    }

    protected function poolFromSettingPercentage(float $unitCommissionAmount, float $settingPercentage): float
    {
        if ($settingPercentage <= 0) {
            return 0.0;
        }

        return round($unitCommissionAmount * $settingPercentage / 100, 2);
    }

    protected function shareOfUnitCommission(float $amount, float $unitCommissionAmount): float
    {
        if ($unitCommissionAmount <= 0) {
            return 0.0;
        }

        return round($amount / $unitCommissionAmount * 100, 4);
    }

    /**
     * @param  list<array{user: User, weight: float}>  $entries
     * @return list<array{user: User, amount: float}>
     */
    protected function splitPoolByWeight(float $pool, array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        usort($entries, fn ($a, $b) => $a['user']->id <=> $b['user']->id);

        $tw = array_sum(array_map(fn ($e) => (float) $e['weight'], $entries));
        if ($tw <= 0) {
            return [];
        }

        $n = count($entries);
        $out = [];
        $allocated = 0.0;

        for ($i = 0; $i < $n - 1; $i++) {
            /** @var array{user: User, weight: float} $entry */
            $entry = $entries[$i];
            $w = (float) $entry['weight'];
            $share = round($pool * $w / $tw, 2);
            $out[] = ['user' => $entry['user'], 'amount' => $share];
            $allocated += $share;
        }

        /** @var array{user: User, weight: float} $lastEntry */
        $lastEntry = $entries[$n - 1];
        $lastAmount = round($pool - $allocated, 2);
        $out[] = ['user' => $lastEntry['user'], 'amount' => $lastAmount];

        return $out;
    }
}