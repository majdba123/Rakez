<?php

namespace App\Services\Marketing;

use App\Models\Contract;
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

        $rawContract = $contract->toArray();

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
            'district' => $detailEnrichment['district'] ?? ($contract->district?->toArray()),
        ];

        $teamsAndAssignments = [
            'teams' => $contract->teams->values()->toArray(),
            'responsible_sales_teams' => $responsibleSalesTeams,
            'sales_project_assignments' => $contract->salesProjectAssignments->values()->toArray(),
        ];

        $marketingDetails = [
            'marketing_project' => $contract->marketingProject?->toArray(),
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
                'units' => $contract->units ?? [],
                'legacy_contract_units_summary' => $legacySummary,
                'actual_contract_units' => $unitPayload['actual_contract_units'],
                'all_contract_units' => $unitPayload['actual_contract_units'],
                'contract_units' => $unitPayload['actual_contract_units'],
                'available_contract_units' => $unitPayload['available_contract_units'],
                'unit_statistics' => $unitPayload['unit_statistics'],
                'project_media' => $media['project_media'],
                'media_links' => $media['media_links'],
                'teams' => $teamsAndAssignments['teams'],
                'responsible_sales_teams' => $responsibleSalesTeams,
                'sales_project_assignments' => $teamsAndAssignments['sales_project_assignments'],
                'marketing_project' => $marketingDetails['marketing_project'],
                'duration_status' => $durationStatus,
                'pricing_source' => $pricingSource,
                'commission_value' => $pricingSource['commission_value'],
                'total_unit_price' => $pricingSource['total_unit_price'],
                'average_unit_price' => $pricingSource['average_unit_price'],
                'average_unit_price_all' => $pricingSource['average_unit_price_all'],
                'average_unit_price_available' => $pricingSource['average_unit_price_available'],
                'total_unit_price_all_sum' => $pricingSource['total_unit_price_all_sum'],
                'total_unit_price_available_sum' => $pricingSource['total_unit_price_available_sum'],
            ]
        );

        return array_merge($rawContract, $conceptualBlocks, $compatibilityAliases);
    }

    /**
     * @param  array<string, mixed>  $pricingBasis
     * @return array<string, mixed>
     */
    private function metricsFromPricingBasis(Contract $contract, array $pricingBasis): array
    {
        $metrics = $this->metricsResolver->resolveForShow($contract);

        return array_merge($metrics, [
            'units_count' => [
                'available' => (int) ($pricingBasis['available_units_count'] ?? 0),
                'pending' => $contract->contractUnits->where('status', 'pending')->count(),
            ],
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

        $unitStatistics = [
            'source' => 'actual_contract_units',
            'all_units_count' => (int) ($pricingBasis['all_units_count'] ?? $units->count()),
            'available_units_count' => (int) ($pricingBasis['available_units_count'] ?? $availableUnits->count()),
            'pending_units_count' => $units->where('status', 'pending')->count(),
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
                'all_contract_units' => $units->toArray(),
                'available_contract_units' => $availableUnits->toArray(),
                'unit_statistics' => $unitStatistics,
            ],
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
