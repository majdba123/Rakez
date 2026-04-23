<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use App\Models\Team;
use Illuminate\Support\Collection;

class MarketingProjectDetailAssembler
{
    public function __construct(
        private ContractPricingBasisService $pricingBasisService,
        private MarketingBudgetCalculationService $budgetCalculationService,
        private MarketingProjectMetricsResolver $metricsResolver,
    ) {}

    /**
     * Build the marketing project detail response from one loaded contract graph.
     *
     * Top-level legacy keys are kept as compatibility aliases. The nested blocks
     * document the source of truth and prevent relational units from being mixed
     * with the legacy contracts.units JSON summary.
     *
     * @param  array<string, mixed>  $durationStatus
     * @param  array<int, mixed>  $responsibleSalesTeams
     * @param  array<string, mixed>  $detailEnrichment
     * @return array<string, mixed>
     */
    public function assemble(
        Contract $contract,
        array $durationStatus,
        array $responsibleSalesTeams,
        array $detailEnrichment = []
    ): array {
        $contract->loadMissing([
            'info',
            'projectMedia',
            'contractUnits',
            'city',
            'district',
            'teams',
            'salesProjectAssignments.leader.team',
            'marketingProject.teamLeader.team',
            'marketingProject.teams.user.team',
            'marketingProject.developerPlan',
            'marketingProject.employeePlans.user',
            'marketingProject.expectedBooking',
        ]);

        $pricingBasis = $this->pricingBasisService->resolveForMarketingProjectDetail($contract);
        $metrics = $this->metricsFromPricingBasis($contract, $pricingBasis);
        $pricingSource = $this->pricingSource($contract, $pricingBasis, $metrics);
        $unitPayload = $this->unitPayload($contract, $pricingBasis);
        $media = $this->mediaPayload($contract->projectMedia);

        $legacySummary = [
            'source' => 'contracts.units',
            'items' => $contract->units ?? [],
        ];

        $identity = [
            'id' => (int) $contract->id,
            'contract_id' => (int) $contract->id,
            'project_name' => $contract->project_name,
            'status' => $contract->status,
            'contract_number' => $metrics['contract_number'],
            'advertiser' => $metrics['advertiser'],
            'created_at' => $contract->created_at,
            'updated_at' => $contract->updated_at,
        ];

        $developer = [
            'name' => $contract->developer_name,
            'number' => $contract->developer_number,
            'requirements' => $contract->developer_requiment,
        ];

        $location = [
            'city' => $detailEnrichment['city'] ?? ($contract->city?->toArray()),
            'district' => $contract->district?->toArray(),
            'address' => $detailEnrichment['address'] ?? (
                $contract->info?->second_party_address
                    ? [
                        'source' => 'contract_infos.second_party_address',
                        'value' => $contract->info->second_party_address,
                    ]
                    : null
            ),
            'location_url' => $contract->info?->location_url,
        ];

        $teamsAndAssignments = [
            'teams' => $this->teamPayload($contract->teams),
            'responsible_sales_teams' => $responsibleSalesTeams,
            'sales_project_assignments' => $this->salesAssignmentPayload($contract->salesProjectAssignments),
        ];

        $marketingDetails = [
            'marketing_project' => $this->marketingProjectPayload($contract->marketingProject),
            'developer_plan' => $contract->marketingProject?->developerPlan?->toArray(),
            'employee_plans' => $contract->marketingProject?->employeePlans?->values()->toArray() ?? [],
            'expected_booking' => $contract->marketingProject?->expectedBooking?->toArray(),
        ];

        $conceptualBlocks = [
            'identity' => $identity,
            'developer' => $developer,
            'media' => $media,
            'location' => $location,
            'legacy_summary' => [
                'legacy_contract_units_summary' => $legacySummary,
            ],
            'actual_unit_data' => $unitPayload['actual_unit_data'],
            'teams_and_assignments' => $teamsAndAssignments,
            'marketing_details' => $marketingDetails,
            'pricing' => $this->pricingBlock($pricingSource, $pricingBasis),
            'duration' => $durationStatus,
        ];

        $compatibilityAliases = array_merge(
            $identity,
            $metrics,
            [
                'developer_name' => $contract->developer_name,
                'developer_number' => $contract->developer_number,
                'city' => $location['city'],
                'district' => $location['district'],
                'legacy_contract_units_summary' => $legacySummary,
                'contract_units' => $unitPayload['actual_contract_units'],
                'project_media' => $media['project_media'],
                'media_links' => $media['media_links'],
                'teams' => $teamsAndAssignments['teams'],
                'responsible_sales_teams' => $responsibleSalesTeams,
                'sales_project_assignments' => $teamsAndAssignments['sales_project_assignments'],
                'marketing_project' => $marketingDetails['marketing_project'],
                'duration_status' => $durationStatus,
                'pricing_source' => $pricingSource,
            ]
        );

        return array_merge($conceptualBlocks, $compatibilityAliases);
    }

    /**
     * @param  array<string, mixed>  $pricingBasis
     * @return array<string, mixed>
     */
    private function metricsFromPricingBasis(Contract $contract, array $pricingBasis): array
    {
        $metrics = $this->metricsResolver->resolveForShow($contract);

        return array_merge($metrics, [
            'units_count' => $this->metricsResolver->unitStatusCounts($contract->contractUnits),
            'avg_unit_price' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
            'total_available_value' => (float) ($pricingBasis['total_unit_price_available_sum'] ?? 0),
            'commission_percent' => (float) $contract->getEffectiveCommissionPercent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $pricingBasis
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    private function pricingSource(Contract $contract, array $pricingBasis, array $metrics): array
    {
        $info = $contract->info;
        $commissionValue = $this->budgetCalculationService->commissionValueFromPricingBasis($contract, $pricingBasis);

        return [
            'contract_id' => (int) $contract->id,
            'contract_number' => $info?->contract_number,
            'project_name' => $contract->project_name,
            'source_of_truth' => $this->pricingSourceOfTruth($pricingBasis),
            'commission_percent' => (float) $metrics['commission_percent'],
            'commission_value' => $commissionValue,
            'total_unit_price' => (float) $pricingBasis[ContractPricingBasisService::COMMISSION_BASE_KEY],
            'total_available_value' => (float) $metrics['total_available_value'],
            'total_unit_price_all_sum' => (float) ($pricingBasis['total_unit_price_all_sum'] ?? 0),
            'total_unit_price_available_sum' => (float) ($pricingBasis['total_unit_price_available_sum'] ?? 0),
            'average_unit_price' => (float) ($pricingBasis['average_unit_price'] ?? 0),
            'average_unit_price_all' => (float) ($pricingBasis['average_unit_price_all'] ?? 0),
            'average_unit_price_available' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
            'pricing_basis' => $pricingBasis,
            'field_meanings' => $this->pricingFieldMeanings(),
            'agreement_duration_days' => $info ? (int) ($info->agreement_duration_days ?? 0) : null,
            'agreement_duration_months' => $info ? (int) ($info->agreement_duration_months ?? 0) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $pricingSource
     * @param  array<string, mixed>  $pricingBasis
     * @return array<string, mixed>
     */
    private function pricingBlock(array $pricingSource, array $pricingBasis): array
    {
        return [
            'source_of_truth' => $pricingSource['source_of_truth'],
            'field_meanings' => $pricingSource['field_meanings'],
            'pricing_source' => $pricingSource,
            'basis' => $pricingBasis,
        ];
    }

    /**
     * @param  array<string, mixed>  $pricingBasis
     */
    private function pricingSourceOfTruth(array $pricingBasis): string
    {
        if (! empty($pricingBasis['has_actual_contract_units'])) {
            return 'actual_contract_units';
        }

        if (! empty($pricingBasis['stored_fallback_applied'])) {
            return 'contract_infos.avg_property_value';
        }

        return 'none';
    }

    /**
     * @return array<string, string>
     */
    private function pricingFieldMeanings(): array
    {
        return [
            'avg_unit_price' => 'Alias of average_unit_price_available; average price of available actual_contract_units, or 0 when none are available.',
            'average_unit_price_available' => 'Average price of actual contract_units where status is available.',
            'average_unit_price_all' => 'Average price of all linked non-deleted actual contract_units.',
            'total_available_value' => 'Sum of prices for available actual contract_units.',
            'total_unit_price' => 'Commission base. For project details this is available actual unit sum; stored contract info fallback is allowed only when no actual contract_units exist.',
            'commission_value' => 'total_unit_price multiplied by the contract commission_percent.',
        ];
    }

    /**
     * @param  array<string, mixed>  $pricingBasis
     * @return array<string, mixed>
     */
    private function unitPayload(Contract $contract, array $pricingBasis): array
    {
        $units = $contract->contractUnits->values();
        $availableUnits = $units->where('status', 'available')->values();
        $unitCounts = $this->metricsResolver->unitStatusCounts($units);

        $unitStatistics = [
            'source' => 'actual_contract_units',
            'all_units_count' => (int) ($pricingBasis['all_units_count'] ?? $units->count()),
            'available_units_count' => (int) ($pricingBasis['available_units_count'] ?? $availableUnits->count()),
            'pending_units_count' => $unitCounts['pending'],
            'reserved_units_count' => $unitCounts['reserved'],
            'sold_units_count' => $unitCounts['sold'],
            'units_count' => $unitCounts,
            'status_counts' => $unitCounts['by_status'],
            'total_unit_price_all_sum' => (float) ($pricingBasis['total_unit_price_all_sum'] ?? 0),
            'total_unit_price_available_sum' => (float) ($pricingBasis['total_unit_price_available_sum'] ?? 0),
            'average_unit_price' => (float) ($pricingBasis['average_unit_price'] ?? 0),
            'average_unit_price_available' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
            'average_unit_price_all' => (float) ($pricingBasis['average_unit_price_all'] ?? 0),
            'basis' => [
                'actual_contract_units' => 'all linked non-deleted contract_units rows',
                'available_contract_units' => 'actual_contract_units where status is available',
                'avg_unit_price' => 'available_contract_units.price average',
                'total_available_value' => 'available_contract_units.price sum',
            ],
        ];

        return [
            'actual_contract_units' => $units->toArray(),
            'available_contract_units' => $availableUnits->toArray(),
            'unit_statistics' => $unitStatistics,
            'actual_unit_data' => [
                'source' => 'contract_units',
                'meaning' => 'Actual linked non-deleted contract_units rows. This is distinct from legacy_summary.legacy_contract_units_summary.',
                'all_contract_units' => $units->toArray(),
                'available_contract_units' => $availableUnits->toArray(),
                'units_count' => $unitCounts,
                'unit_statistics' => $unitStatistics,
            ],
        ];
    }

    /**
     * @param  Collection<int, mixed>  $teams
     * @return array<int, array<string, mixed>>
     */
    private function teamPayload(Collection $teams): array
    {
        return $teams
            ->map(fn ($team) => [
                'id' => $team->id,
                'code' => $team->code ?? null,
                'name' => $team->name,
                'description' => $team->description ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $assignments
     * @return array<int, array<string, mixed>>
     */
    private function salesAssignmentPayload(Collection $assignments): array
    {
        return $assignments
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'contract_id' => $assignment->contract_id,
                'leader_id' => $assignment->leader_id,
                'assigned_by' => $assignment->assigned_by,
                'start_date' => $assignment->start_date,
                'end_date' => $assignment->end_date,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
                'leader' => $this->userPayload($assignment->leader),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function marketingProjectPayload(mixed $project): ?array
    {
        if (! $project) {
            return null;
        }

        return [
            'id' => $project->id,
            'contract_id' => $project->contract_id,
            'status' => $project->status,
            'assigned_team_leader' => $project->assigned_team_leader,
            'team_leader' => $this->userPayload($project->teamLeader),
            'teams' => $project->teams
                ? $project->teams
                    ->map(fn ($assignment) => [
                        'id' => $assignment->id,
                        'marketing_project_id' => $assignment->marketing_project_id,
                        'user_id' => $assignment->user_id,
                        'role' => $assignment->role,
                        'user' => $this->userPayload($assignment->user),
                    ])
                    ->values()
                    ->all()
                : [],
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userPayload(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        $team = $user->getRelationValue('team');
        if (! $team && $user->team_id) {
            $team = Team::query()->find($user->team_id);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email ?? null,
            'type' => $user->type ?? null,
            'team_id' => $user->team_id ?? null,
            'team' => $team ? [
                'id' => $team->id,
                'code' => $team->code ?? null,
                'name' => $team->name,
                'description' => $team->description ?? null,
            ] : null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $media
     * @return array<string, mixed>
     */
    private function mediaPayload(Collection $media): array
    {
        $projectMedia = $media
            ->filter(fn ($item) => ! empty($item->url))
            ->groupBy(fn ($item) => strtolower((string) $item->type) . '|' . (string) $item->url)
            ->map(function (Collection $group) {
                $first = $group->first();
                $departments = $group
                    ->pluck('department')
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $first->id,
                    'type' => $first->type,
                    'url' => $first->url,
                    'department' => $departments->first(),
                    'departments' => $departments->all(),
                    'source_media_ids' => $group->pluck('id')->values()->all(),
                ];
            })
            ->values();

        return [
            'deduplication_rule' => 'unique by type and url; departments and source_media_ids preserve source row context',
            'project_media' => $projectMedia->all(),
            'media_links' => $projectMedia
                ->map(fn (array $item) => [
                    'type' => $item['type'],
                    'url' => $item['url'],
                    'department' => $item['department'],
                    'departments' => $item['departments'],
                ])
                ->values()
                ->all(),
        ];
    }
}
