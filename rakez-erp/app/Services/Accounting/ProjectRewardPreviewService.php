<?php

namespace App\Services\Accounting;

use App\Models\ProjectRewardSetting;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProjectRewardPreviewService
{
    public function __construct(
        private ProjectRewardCalculator $calculator,
    ) {}

    /**
     * @param  array{manual_amount?: float|null, reward_percentage?: float|null}  $options
     * @return array<string, mixed>
     */
    public function preview(SalesReservation $reservation, array $options = []): array
    {
        $reservation->loadMissing([
            'contract.teams',
            'contract.activeProjectRewardSetting',
            'contractUnit',
            'participantRecords.user.team',
        ]);

        $contract = $reservation->contract;
        if (!$contract) {
            throw ValidationException::withMessages(['reservation' => 'Reservation has no contract.']);
        }

        /** @var ProjectRewardSetting|null $setting */
        $setting = $contract->activeProjectRewardSetting;
        if (!$setting instanceof ProjectRewardSetting) {
            throw ValidationException::withMessages([
                'project_reward_setting' => 'No active project reward setting for this project.',
            ]);
        }

        $setting->loadMissing([
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ]);

        $rewardPercentage = array_key_exists('reward_percentage', $options)
            ? (float) $options['reward_percentage']
            : ($setting->reward_percentage !== null ? (float) $setting->reward_percentage : null);

        $base = $this->calculator->calculateBaseAmount(
            (string) $setting->calculation_mode,
            isset($options['manual_amount']) ? (float) $options['manual_amount'] : null,
            $rewardPercentage,
            $reservation,
        );

        $vat = $this->calculator->calculateVat(
            (float) $base['base_amount'],
            (bool) $setting->tax_enabled,
            (float) $setting->vat_percentage,
        );

        $poolAmount = (float) $vat['distribution_pool_amount'];
        $constructed = $this->buildParticipantAndManagementRecipients($reservation, $setting, $poolAmount);

        $totalDistributed = round(array_sum(array_column($constructed['recipients'], 'amount')), 2);
        $unresolvedTotal = round(array_sum(array_column($constructed['unresolved'], 'amount')), 2);
        $remaining = round($poolAmount - $totalDistributed - $unresolvedTotal, 2);
        if ($remaining < 0 && $remaining > -0.02) {
            $remaining = 0.0;
        }

        return [
            'reservation_id' => (int) $reservation->id,
            'contract_id' => (int) $contract->id,
            'project_reward_setting_id' => (int) $setting->id,
            'calculation_mode' => $base['calculation_mode'],
            'calculation_base_amount' => $base['calculation_base_amount'],
            'reward_percentage' => $base['reward_percentage'],
            'source' => $setting->source,
            'base_amount' => $base['base_amount'],
            'tax_enabled' => $vat['tax_enabled'],
            'vat_percentage' => $vat['vat_percentage'],
            'vat_amount' => $vat['vat_amount'],
            'total_amount' => $vat['total_amount'],
            'distribution_pool_amount' => $poolAmount,
            'recipients' => $constructed['recipients'],
            'unresolved' => $constructed['unresolved'],
            'total_distributed' => $totalDistributed,
            'remaining_amount' => max(0, $remaining),
        ];
    }

    /**
     * @return array{recipients: list<array<string, mixed>>, unresolved: list<array<string, mixed>>}
     */
    protected function buildParticipantAndManagementRecipients(
        SalesReservation $reservation,
        ProjectRewardSetting $setting,
        float $poolAmount,
    ): array {
        $recipients = [];
        $unresolved = [];

        $projectTeamIds = $reservation->contract->teams->pluck('id')->map(fn ($id) => (int) $id)->all();

        /** @var array<string, list<array{user: User, weight: float, participant_id: int}>> $bucketMembers */
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
                $bucketMembers[$key] ??= [];
                $bucketMembers[$key][] = [
                    'user' => $user,
                    'weight' => $weight,
                    'participant_id' => (int) $record->id,
                ];
            }
        }

        foreach ($this->bucketDefinitions($setting) as $def) {
            $key = $def['scope'].'|'.$def['type'];
            $members = $bucketMembers[$key] ?? [];
            $pool = $this->poolFromSettingPercentage($poolAmount, (float) $def['setting_percentage']);

            if ($pool <= 0) {
                continue;
            }

            if ($members === []) {
                $unresolved[] = [
                    'source_scope' => $def['scope'],
                    'source_type' => $def['type'],
                    'percentage' => $this->shareOfPool($pool, $poolAmount),
                    'amount' => $pool,
                    'reason' => 'no_matching_participants',
                ];

                continue;
            }

            foreach ($this->splitPoolByWeight($pool, $members) as $row) {
                $recipients[] = [
                    'user_id' => $row['user']->id,
                    'recipient_type' => 'participant',
                    'sales_reservation_id' => (int) $reservation->id,
                    'sales_reservation_participant_id' => $row['participant_id'],
                    'user' => [
                        'id' => $row['user']->id,
                        'name' => $row['user']->name,
                    ],
                    'source_scope' => $def['scope'],
                    'source_type' => $def['type'],
                    'percentage' => $this->shareOfPool($row['amount'], $poolAmount),
                    'amount' => $row['amount'],
                ];
            }
        }

        foreach ($this->managementDefinitions($setting) as $row) {
            $pct = (float) $row['percentage'];
            if ($pct <= 0) {
                continue;
            }

            if (!$row['user'] instanceof User) {
                $amount = $this->poolFromSettingPercentage($poolAmount, $pct);
                $unresolved[] = [
                    'source_scope' => 'management',
                    'source_type' => $row['type'],
                    'percentage' => $this->shareOfPool($amount, $poolAmount),
                    'amount' => $amount,
                    'reason' => 'missing_management_user',
                ];
                continue;
            }

            $amount = round($poolAmount * $pct / 100, 2);
            $recipients[] = [
                'user_id' => $row['user']->id,
                'recipient_type' => 'management',
                'sales_reservation_id' => (int) $reservation->id,
                'sales_reservation_participant_id' => null,
                'user' => [
                    'id' => $row['user']->id,
                    'name' => $row['user']->name,
                ],
                'source_scope' => 'management',
                'source_type' => $row['type'],
                'percentage' => $this->shareOfPool($amount, $poolAmount),
                'amount' => $amount,
            ];
        }

        return compact('recipients', 'unresolved');
    }

    /**
     * @return list<array{scope: string, type: string, setting_percentage: float}>
     */
    protected function bucketDefinitions(ProjectRewardSetting $setting): array
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
    protected function managementDefinitions(ProjectRewardSetting $setting): array
    {
        return [
            ['type' => 'ceo', 'percentage' => (float) $setting->ceo_percentage, 'user' => $setting->ceoUser],
            ['type' => 'sales_manager', 'percentage' => (float) $setting->sales_manager_percentage, 'user' => $setting->salesManagerUser],
            ['type' => 'sales_leader', 'percentage' => (float) $setting->sales_leader_percentage, 'user' => $setting->salesLeaderUser],
            ['type' => 'group_leader', 'percentage' => (float) $setting->group_leader_percentage, 'user' => $setting->groupLeaderUser],
            ['type' => 'external_marketer', 'percentage' => (float) $setting->external_marketer_percentage, 'user' => $setting->externalMarketerUser],
        ];
    }

    protected function poolFromSettingPercentage(float $poolAmount, float $settingPercentage): float
    {
        if ($settingPercentage <= 0) {
            return 0.0;
        }

        return round($poolAmount * $settingPercentage / 100, 2);
    }

    protected function shareOfPool(float $amount, float $poolAmount): float
    {
        if ($poolAmount <= 0) {
            return 0.0;
        }

        return round($amount / $poolAmount * 100, 4);
    }

    /**
     * @param  list<array{user: User, weight: float, participant_id: int}>  $entries
     * @return list<array{user: User, participant_id: int, amount: float}>
     */
    protected function splitPoolByWeight(float $pool, array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        usort($entries, fn ($a, $b) => $a['user']->id <=> $b['user']->id);

        $totalWeight = array_sum(array_map(fn ($entry) => (float) $entry['weight'], $entries));
        if ($totalWeight <= 0) {
            return [];
        }

        $out = [];
        $allocated = 0.0;
        $lastIndex = count($entries) - 1;

        for ($i = 0; $i < $lastIndex; $i++) {
            $entry = $entries[$i];
            $share = round($pool * (float) $entry['weight'] / $totalWeight, 2);
            $out[] = [
                'user' => $entry['user'],
                'participant_id' => (int) $entry['participant_id'],
                'amount' => $share,
            ];
            $allocated += $share;
        }

        $lastEntry = $entries[$lastIndex];
        $out[] = [
            'user' => $lastEntry['user'],
            'participant_id' => (int) $lastEntry['participant_id'],
            'amount' => round($pool - $allocated, 2),
        ];

        return $out;
    }
}
