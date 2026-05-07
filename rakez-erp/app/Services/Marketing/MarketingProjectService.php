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
                'teamLeader.team',
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
            'marketingProject.teamLeader.team',
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
            'teamLeader.team',
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

        if ($info?->second_party_address) {
            $out['address'] = [
                'source' => 'contract_infos.second_party_address',
                'value' => $info->second_party_address,
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
