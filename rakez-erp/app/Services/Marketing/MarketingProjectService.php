<?php

namespace App\Services\Marketing;

use App\Enums\ContractWorkflowStatus;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\Team;
use App\Models\User;
use App\Models\MarketingProject;
use App\Services\Sales\SalesTeamService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MarketingProjectService
{
    public function __construct(
        private MarketingProjectBootstrapService $bootstrapService,
        private SalesTeamService $salesTeamService,
        private ContractPricingBasisService $pricingBasisService,
        private MarketingBudgetCalculationService $budgetCalculationService,
        private MarketingProjectMetricsResolver $metricsResolver,
    ) {}

    /**
     * Marketing projects = contracts with status = completed.
     * Marketing user sees all completed contracts (no team/user filter).
     */
    /** @deprecated Prefer {@see ContractWorkflowStatus::Completed} */
    public const COMPLETED_CONTRACT_STATUS = 'completed';

    /**
     * Get canonical shared metrics for a contract.
     * Used by both list and show endpoints to ensure numeric consistency.
     *
     * @return array<string, mixed>
     */
    public function getCanonicalMetrics(Contract $contract): array
    {
        return $this->metricsResolver->resolveForShow($contract);
    }

    /**
     * Get all marketing projects whose contracts are completed. Every marketing user sees the same list.
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProjectsWithCompletedContracts(int $perPage = 15)
    {
        return $this->getProjects([], $perPage);
    }

    /**
     * Marketing projects list:
     * - Base: contracts with status=completed
     * - No assignment/team filter (every marketing user sees same list)
     * - Optional filters: q/city/district applied on contract fields
     * - Optional status filter applied on contract units status (available/pending/reserved/sold)
     */
    public function getProjects(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Contract::query()
            ->where('status', ContractWorkflowStatus::Completed->value)
            ->with([
                'info',
                'projectMedia',
                'secondPartyData',
                'contractUnits',
                'city',
                'district',
            ])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['q'])) {
            $query->where('project_name', 'like', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (!empty($filters['district_id'])) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        // Units status filter (available/reserved/sold/pending)
        if (!empty($filters['status'])) {
            $query->whereHas('contractUnits', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        $contracts = $query->paginate($perPage);
        $contractCollection = $contracts->getCollection();

        $this->bootstrapService->ensureForContracts($contractCollection);

        $projectsByContractId = $this->bootstrapService->getProjectsByContractIds(
            $contractCollection->pluck('id')
        );

        $marketingProjects = $contractCollection
            ->map(function (Contract $contract) use ($projectsByContractId) {
                $project = $projectsByContractId->get($contract->id);

                if (!$project) {
                    return null;
                }

                $project->setRelation('contract', $contract);

                return $project;
            })
            ->filter()
            ->values();

        $contracts->setCollection($marketingProjects);

        return $contracts;
    }

    public function getProjectDetails($projectId)
    {
        $project = MarketingProject::query()
            ->with($this->marketingProjectDetailRelations())
            ->find($projectId);

        if ($project?->contract) {
            $contract = $project->contract;
        } else {
            $contract = Contract::query()
                ->with($this->contractDetailRelations())
                ->findOrFail($projectId);

            $project = $this->bootstrapService->ensureForCompletedContract($contract);
        }

        if ($project) {
            $project->loadMissing([
                'teamLeader',
                'teams.user.team',
                'developerPlan',
                'employeePlans.user',
                'expectedBooking',
            ]);

            $contract->setRelation('marketingProject', $project);
        }

        $contract->loadMissing($this->contractDetailRelations());
        $this->hydrateTeamsFromSalesAndMarketingAssignments($contract);

        return $contract;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function contractDetailRelations(): array
    {
        return [
            'info',
            'projectMedia',
            'contractUnits',
            'city',
            'district',
            'teams',
            'salesProjectAssignments.leader.team',
            'marketingProject.teamLeader',
            'marketingProject.teams.user.team',
            'marketingProject.developerPlan',
            'marketingProject.employeePlans.user',
            'marketingProject.expectedBooking',
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function marketingProjectDetailRelations(): array
    {
        return [
            'teamLeader',
            'teams.user.team',
            'developerPlan',
            'employeePlans.user',
            'expectedBooking',
            'contract' => fn ($query) => $query->with($this->contractDetailRelations()),
        ];
    }

    private function hydrateTeamsFromSalesAndMarketingAssignments(Contract $contract): void
    {
        $contract->loadMissing([
            'teams',
            'salesProjectAssignments.leader.team',
            'marketingProject.teams.user.team',
        ]);

        $teams = new EloquentCollection($contract->teams->all());

        $assignmentTeamIds = $contract->salesProjectAssignments
            ->map(fn ($assignment) => $assignment->leader?->team_id)
            ->filter()
            ->unique()
            ->values();

        $marketingProjectTeamIds = $contract->marketingProject?->teams
            ? $contract->marketingProject->teams
                ->map(fn ($assignment) => $assignment->user?->team_id)
                ->filter()
                ->unique()
                ->values()
            : collect();

        $inferredTeams = Team::query()
            ->whereIn('id', $assignmentTeamIds->merge($marketingProjectTeamIds)->unique()->values())
            ->get();

        foreach ($inferredTeams as $team) {
            if (! $teams->contains(fn (Team $existing) => (int) $existing->id === (int) $team->id)) {
                $teams->push($team);
            }
        }

        $contract->setRelation('teams', $teams->values());
    }

    /**
     * Fill display-only fields when FK-based relations are null but contract_infos has text (e.g. city name).
     *
     * @return array<string, mixed>
     */
    public function enrichContractDetailForMarketingApi(Contract $contract): array
    {
        $contract->loadMissing(['info', 'city', 'district']);
        $out = [];
        $info = $contract->info;

        if ($contract->relationLoaded('city') && $contract->getRelation('city') === null && $info?->contract_city) {
            $out['city'] = [
                'id' => null,
                'name' => $info->contract_city,
            ];
        }

        if (
            $contract->relationLoaded('district')
            && $contract->getRelation('district') === null
            && $info?->second_party_address
        ) {
            $out['district'] = [
                'id' => null,
                'name' => $info->second_party_address,
            ];
        }

        return $out;
    }

    /**
     * When no active sales_project_assignment exists, infer leaders from marketing project assignee, team creator, managers, or first sales member.
     *
     * @param  Collection<int, \App\Models\SalesProjectAssignment>  $assignments
     * @return Collection<int, User>
     */
    private function resolveSalesLeadersForTeam(
        Collection $assignments,
        Team $team,
        ?MarketingProject $marketingProject
    ): Collection {
        $fromAssignments = $assignments->filter(function ($a) use ($team) {
            return $a->leader && (int) $a->leader->team_id === (int) $team->id;
        })->map(fn ($a) => $a->leader)->unique('id')->values();

        if ($fromAssignments->isNotEmpty()) {
            return $fromAssignments;
        }

        $candidates = collect();

        if ($marketingProject?->assigned_team_leader) {
            $u = User::query()
                ->whereKey($marketingProject->assigned_team_leader)
                ->where('team_id', $team->id)
                ->where('type', 'sales')
                ->where('is_active', true)
                ->first();
            if ($u) {
                $candidates->push($u);
            }
        }

        foreach ($this->salesTeamService->getDefaultSalesLeadersForTeam($team) as $u) {
            if (!$candidates->contains(fn ($x) => (int) $x->id === (int) $u->id)) {
                $candidates->push($u);
            }
        }

        return $candidates->unique('id')->values();
    }

    /**
     * Responsible sales teams for a contract (from contract_team + sales_project_assignments), with leaders, members, and leader ratings (sales domain).
     *
     * @return array<int, array{id:int,name:string,leaders:array,members:array<int, array{id:int,name:string,role:string,rating:?int}>}>
     */
    public function buildResponsibleSalesTeams(Contract $contract): array
    {
        $contract->loadMissing([
            'teams',
            'marketingProject',
            'salesProjectAssignments.leader.team',
        ]);

        $this->hydrateTeamsFromSalesAndMarketingAssignments($contract);

        $assignments = $contract->salesProjectAssignments;
        $teams = $contract->teams;
        $marketingProject = $contract->marketingProject;

        if ($teams->isEmpty() && $assignments->isNotEmpty()) {
            $teamIds = $assignments->map(fn ($a) => $a->leader?->team_id)->filter()->unique()->values();
            if ($teamIds->isNotEmpty()) {
                $teams = Team::whereIn('id', $teamIds)->get();
            }
        }

        if ($teams->isEmpty()) {
            return [];
        }

        $out = [];
        foreach ($teams as $team) {
            $leaders = $this->resolveSalesLeadersForTeam($assignments, $team, $marketingProject);

            $leaderIds = $leaders->pluck('id')->all();
            $primaryLeader = $leaders->first();

            $memberUsers = User::query()
                ->where('team_id', $team->id)
                ->where('type', 'sales')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $ratingsByMember = collect();
            if ($primaryLeader) {
                $ratingsByMember = $this->salesTeamService->getLeaderRatingsKeyedByMember(
                    $primaryLeader->id,
                    $memberUsers->pluck('id')->all()
                );
            }

            $membersPayload = $memberUsers->map(function ($u) use ($ratingsByMember, $leaderIds) {
                $rating = $ratingsByMember->get($u->id);
                $isLeader = in_array($u->id, $leaderIds, true);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $isLeader ? 'leader' : 'member',
                    'rating' => $rating?->rating,
                ];
            })->values()->all();

            $leadersPayload = $leaders->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
            ])->values()->all();

            $out[] = [
                'id' => $team->id,
                'name' => $team->name,
                'leaders' => $leadersPayload,
                'members' => $membersPayload,
            ];
        }

        return $out;
    }

    /**
     * Get summary fields for a contract (location, contract_number, units_count, pricing, etc.)
     * for use in list and detail API responses.
     */
    public function getContractSummaryFields(Contract $contract): array
    {
        $contract->loadMissing(['info', 'contractUnits', 'city', 'district']);
        $info = $contract->info;
        $units = $contract->relationLoaded('contractUnits')
            ? $contract->getRelation('contractUnits')
            : $contract->contractUnits()->get();
        $availableUnits = $units->where('status', 'available');
        $pendingUnits = $units->where('status', 'pending');

        $locationParts = array_filter([$contract->city?->name, $contract->district?->name]);
        $location = $locationParts ? trim(implode(', ', $locationParts)) : null;

        return [
            'location' => $location,
            'city' => $contract->city?->name,
            'district' => $contract->district?->name,
            'contract_number' => $info?->contract_number ?? null,
            'units_count' => [
                'available' => $availableUnits->count(),
                'pending' => $pendingUnits->count(),
            ],
            'avg_unit_price' => $info ? (float) ($info->avg_property_value ?? 0) : 0,
            'commission_percent' => $contract->getEffectiveCommissionPercent(),
            'total_available_value' => (float) $availableUnits->sum('price'),
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number ?? null,
        ];
    }

    /**
     * Financial source for marketing project screens — no marketing % / campaign preview (use POST developer-plans/calculate-budget).
     *
     * @return array<string, mixed>
     */
    public function buildPricingSourceForContract(Contract $contract): array
    {
        $contract->loadMissing(['info', 'contractUnits']);
        $info = $contract->info;
        $pricingBasis = $this->pricingBasisService->resolve($contract, []);
        $commissionValue = $this->budgetCalculationService->commissionValueFromPricingBasis($contract, $pricingBasis);

        // Get canonical metrics
        $metrics = $this->metricsResolver->resolve($contract);

        return [
            'contract_id' => $metrics['contract_id'],
            'contract_number' => $info?->contract_number,
            'project_name' => $metrics['project_name'],
            'commission_percent' => $metrics['commission_percent'],
            'commission_value' => $commissionValue,
            'total_unit_price' => (float) $pricingBasis[ContractPricingBasisService::COMMISSION_BASE_KEY],
            /** Canonical UI average = mean price of ALL units per business rules */
            'average_unit_price' => $metrics['avg_unit_price'],
            'average_unit_price_all' => $metrics['avg_unit_price'],
            'average_unit_price_available' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
            'pricing_basis' => $pricingBasis,
            'agreement_duration_days' => $info ? (int) ($info->agreement_duration_days ?? 0) : null,
            'agreement_duration_months' => $info ? (int) ($info->agreement_duration_months ?? 0) : null,
        ];
    }

    /**
     * Explicit detail-only unit payloads so all linked units and available units are not conflated.
     *
     * @return array<string, mixed>
     */
    public function buildUnitDetailPayload(Contract $contract): array
    {
        $contract->loadMissing(['contractUnits']);

        $units = $contract->contractUnits->values();
        $availableUnits = $units->where('status', 'available')->values();
        $pricingBasis = $this->pricingBasisService->resolve($contract, []);

        return [
            'available_contract_units' => $availableUnits->toArray(),
            'unit_statistics' => [
                'all_units_count' => $units->count(),
                'available_units_count' => $availableUnits->count(),
                'pending_units_count' => $units->where('status', 'pending')->count(),
                'total_unit_price_all_sum' => (float) ($pricingBasis['total_unit_price_all_sum'] ?? 0),
                'total_unit_price_available_sum' => (float) ($pricingBasis['total_unit_price_available_sum'] ?? 0),
                'average_unit_price' => (float) ($pricingBasis['average_unit_price'] ?? 0),
                'average_unit_price_available' => (float) ($pricingBasis['average_unit_price_available'] ?? 0),
                'average_unit_price_all' => (float) ($pricingBasis['average_unit_price_all'] ?? 0),
                'basis' => [
                    'contract_units' => 'all linked non-deleted contract_units rows',
                    'available_contract_units' => 'contract_units where status is available',
                    'avg_unit_price' => 'available_contract_units.price average',
                    'total_available_value' => 'available_contract_units.price sum',
                ],
            ],
        ];
    }

    public function getContractDurationStatus($contractId)
    {
        $contractInfo = ContractInfo::where('contract_id', $contractId)->first();
        if (!$contractInfo) return ['status' => 'unknown', 'days' => 0];

        $startDate = $contractInfo->created_at;
        $durationDays = $contractInfo->agreement_duration_days;
        $endDate = $startDate->copy()->addDays($durationDays);
        $remainingDays = (int) now()->diffInDays($endDate, false);

        if ($remainingDays < 30) {
            return [
                'status' => 'red',
                'label' => 'Less than 1 month remaining',
                'label_ar' => 'أقل من شهر متبقي',
                'days' => $remainingDays
            ];
        } elseif ($remainingDays < 90) {
            return [
                'status' => 'orange',
                'label' => '1-3 months remaining',
                'label_ar' => '1–3 أشهر متبقية',
                'days' => $remainingDays
            ];
        } else {
            return [
                'status' => 'green',
                'label' => 'More than 3 months remaining',
                'label_ar' => 'أكثر من 3 أشهر متبقية',
                'days' => $remainingDays
            ];
        }
    }
}
