<?php

namespace App\Services\Marketing;

use App\Enums\ContractWorkflowStatus;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\Team;
use App\Models\User;
use App\Services\Sales\SalesTeamService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MarketingProjectService
{
    public function __construct(
        private MarketingProjectBootstrapService $bootstrapService
    ) {}

    /**
     * Marketing projects = contracts with status = completed.
     * Marketing user sees all completed contracts (no team/user filter).
     */
    /** @deprecated Prefer {@see ContractWorkflowStatus::Completed} */
    public const COMPLETED_CONTRACT_STATUS = 'completed';

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

    public function getProjectDetails($contractId)
    {
        $contract = Contract::with([
            'info',
            'projectMedia',
            'contractUnits',
            'city',
            'district',
            'teams',
            'salesProjectAssignments' => fn ($q) => $q->active()->with('leader'),
        ])->findOrFail($contractId);

        $project = $this->bootstrapService->ensureForCompletedContract($contract);

        if ($project) {
            $project->loadMissing([
                'teamLeader',
                'teams.user',
                'developerPlan',
                'employeePlans.user',
                'expectedBooking',
            ]);

            $contract->setRelation('marketingProject', $project);
        }

        return $contract;
    }

    /**
     * Responsible sales teams for a contract (from contract_team + sales_project_assignments), with leaders, members, and leader ratings (sales domain).
     *
     * @return array<int, array{id:int,name:string,leaders:array,members:array<int, array{id:int,name:string,role:string,rating:?int}>}>
     */
    public function buildResponsibleSalesTeams(Contract $contract): array
    {
        $salesTeamService = app(SalesTeamService::class);

        $contract->loadMissing([
            'teams',
            'salesProjectAssignments' => fn ($q) => $q->active()->with('leader'),
        ]);

        $assignments = $contract->salesProjectAssignments;
        $teams = $contract->teams;

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
            $leaders = $assignments->filter(function ($a) use ($team) {
                return $a->leader && (int) $a->leader->team_id === (int) $team->id;
            })->map(fn ($a) => $a->leader)->unique('id')->values();

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
                $ratingsByMember = $salesTeamService->getLeaderRatingsKeyedByMember(
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

    public function calculateCampaignBudget($contractId, $inputs)
    {
        $contract = Contract::with('info')->findOrFail($contractId);

        if ($contract->status === ContractWorkflowStatus::Completed->value) {
            $this->bootstrapService->ensureForCompletedContract($contract);
        }

        $info = $contract->info;

        $unitPrice = $inputs['unit_price'] ?? ($info->avg_property_value ?? 0);
        $commissionPercent = $contract->getEffectiveCommissionPercent();

        $commissionValue = $unitPrice * ($commissionPercent / 100);

        // Get marketing percent from inputs, or fallback to 10%
        $marketingPercent = isset($inputs['marketing_percent']) ? (float) $inputs['marketing_percent'] : 10;
        $marketingValue = $commissionValue * ($marketingPercent / 100);

        $durationDays = (int) ($info->agreement_duration_days ?? 30);
        $durationMonths = $this->resolveDurationMonths($info, $durationDays);

        return [
            'commission_percent' => $commissionPercent,
            'commission_value' => $commissionValue,
            'marketing_percent' => $marketingPercent,
            'marketing_value' => $marketingValue,
            'daily_budget' => $this->calculateDailyBudget($marketingValue, $durationDays),
            'monthly_budget' => $this->calculateMonthlyBudget($marketingValue, $durationMonths),
        ];
    }

    public function calculateDailyBudget($marketingValue, $durationDays)
    {
        return $durationDays > 0 ? $marketingValue / $durationDays : 0;
    }

    public function calculateMonthlyBudget($marketingValue, $durationMonths)
    {
        return $durationMonths > 0 ? $marketingValue / $durationMonths : 0;
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

    private function resolveDurationMonths(?ContractInfo $info, int $durationDays): int
    {
        if ($info && !empty($info->agreement_duration_months)) {
            return max(1, (int) $info->agreement_duration_months);
        }

        if ($durationDays <= 0) {
            return 1;
        }

        return (int) ceil($durationDays / 30);
    }
}
