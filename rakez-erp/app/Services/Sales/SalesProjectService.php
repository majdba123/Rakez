<?php

namespace App\Services\Sales;

use App\Enums\ContractUnitWorkflowStatus;
use App\Enums\ContractWorkflowStatus;
use App\Enums\SalesProjectListingStatus;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class SalesProjectService
{
    /**
     * Count units for a contract using the eager-loaded relation when present (avoids N+1).
     */
    protected function countContractUnitsForContract(Contract $contract): int
    {
        if ($contract->relationLoaded('contractUnits')) {
            return $contract->contractUnits->count();
        }

        return $contract->contractUnits()->count();
    }

    /**
     * Attach computed listing fields used by SalesProjectResource (not DB columns).
     */
    protected function applySalesListingComputedFields(Contract $contract): void
    {
        $contract->sales_status = $this->computeProjectSalesStatus($contract);
        $contract->total_units = $this->countContractUnitsForContract($contract);
        $contract->available_units = $this->getAvailableUnitsCount($contract);
        $contract->reserved_units = $this->getReservedUnitsCount($contract);
        $contract->remaining_days = $this->getProjectRemainingDays($contract);
    }

    /**
     * Get projects with sales status computation.
     */
    public function getProjects(array $filters, User $user): LengthAwarePaginator
    {
        // عرض عقود مكتملة ضمنياً فقط في قائمة مشاريع السيلز
        $query = Contract::with([
            'contractUnits',
            'montageDepartment',
            'salesProjectAssignments.leader',
            'user',
            'city',
            'district',
        ])->where('status', ContractWorkflowStatus::Completed->value);

        // Apply filters
        if (! empty($filters['q'])) {
            $query->where('project_name', 'like', '%'.$filters['q'].'%');
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', (int) $filters['city_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', (int) $filters['district_id']);
        }

        // Every sales user sees the same list of completed contracts (SalesProjectController::index does not pass scope).
        // Optional narrowing remains in applyScopeFilter() for future use (e.g. dashboard), not used here.

        $perPage = (int) ($filters['per_page'] ?? 15);
        $paginationOptions = ['path' => request()->url(), 'query' => request()->query()];

        // sales_status is computed in PHP; filter must run before pagination so total/lastPage are correct.
        if (! empty($filters['status'])) {
            $allContracts = $query->orderBy('created_at', 'desc')->get();
            $allContracts->each(fn (Contract $c) => $this->applySalesListingComputedFields($c));
            $filtered = $allContracts->filter(fn (Contract $c) => $c->sales_status === $filters['status'])->values();
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $slice = $filtered->forPage($currentPage, $perPage)->values();

            return new LengthAwarePaginator(
                $slice,
                $filtered->count(),
                $perPage,
                $currentPage,
                $paginationOptions
            );
        }

        $contracts = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $contracts->getCollection()->each(fn (Contract $c) => $this->applySalesListingComputedFields($c));

        return $contracts;
    }

    /**
     * Get project details by ID.
     */
    public function getProjectById(int $contractId): Contract
    {
        $contract = Contract::with([
            'contractUnits',
            'montageDepartment',
            'info',
            'salesProjectAssignments.leader',
            'user',
            'city',
            'district',
        ])->findOrFail($contractId);

        $this->applySalesListingComputedFields($contract);

        return $contract;
    }

    /**
     * Get units for a project with computed availability.
     */
    public function getProjectUnits(int $contractId, array $filters): LengthAwarePaginator
    {
        $contract = Contract::findOrFail($contractId);

        $query = ContractUnit::where('contract_id', $contractId)
            ->with('activeSalesReservations');

        // Apply filters
        if (!empty($filters['floor'])) {
            $query->where('floor', $filters['floor']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $paginationOptions = ['path' => request()->url(), 'query' => request()->query()];
        $projectSalesStatus = $this->computeProjectSalesStatus($contract);

        $enrichUnit = function (ContractUnit $unit) use ($projectSalesStatus) {
            $availability = $this->computeUnitAvailability($unit, $projectSalesStatus);
            $unit->computed_availability = $availability['status'];
            $unit->can_reserve = $availability['can_reserve'];
            $unit->active_reservation = $unit->activeSalesReservations->first();

            return $unit;
        };

        // computed_availability is dynamic; filter before pagination for correct totals.
        if (! empty($filters['status'])) {
            $allUnits = $query->orderBy('unit_number')->get();
            $allUnits->each($enrichUnit);
            $filtered = $allUnits->filter(fn (ContractUnit $u) => $u->computed_availability === $filters['status'])->values();
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $slice = $filtered->forPage($currentPage, $perPage)->values();

            return new LengthAwarePaginator(
                $slice,
                $filtered->count(),
                $perPage,
                $currentPage,
                $paginationOptions
            );
        }

        $units = $query->orderBy('unit_number')->paginate($perPage);
        $units->getCollection()->each($enrichUnit);

        return $units;
    }

    /**
     * Update emergency contacts for a project.
     */
    public function updateEmergencyContacts(int $contractId, array $data, User $user): Contract
    {
        $contract = Contract::findOrFail($contractId);

        // Authorization: Check if user has permission to manage team (leader)
        if (!$user->hasPermissionTo('sales.team.manage') && !$user->hasRole('admin')) {
            throw new \Exception('Unauthorized to update emergency contacts');
        }

        // Additional check: If not admin, must be assigned to this project
        if (!$user->hasRole('admin')) {
            $assignment = \App\Models\SalesProjectAssignment::where('contract_id', $contractId)
                ->where('leader_id', $user->id)
                ->first();

            if (!$assignment) {
                throw new \Exception('Unauthorized: You are not assigned to this project');
            }
        }

        $contract->update([
            'emergency_contact_number' => $data['emergency_contact_number'] ?? $contract->emergency_contact_number,
            'security_guard_number' => $data['security_guard_number'] ?? $contract->security_guard_number,
        ]);

        return $contract->fresh();
    }

    /**
     * Compute sales status for a project.
     */
    protected function computeProjectSalesStatus(Contract $contract): string
    {
        // Check if contract status is completed (available for sales)
        if ($contract->status !== ContractWorkflowStatus::Completed->value) {
            return SalesProjectListingStatus::Pending->value;
        }

        $contract->loadMissing('contractUnits');
        $unitsQuery = $contract->contractUnits();

        if ($unitsQuery->count() === 0) {
            return SalesProjectListingStatus::Pending->value;
        }

        $hasUnpricedUnits = $unitsQuery->where(function ($query) {
            $query->whereNull('price')
                ->orWhere('price', '<=', 0);
        })->exists();

        if ($hasUnpricedUnits) {
            return SalesProjectListingStatus::Pending->value;
        }

        return SalesProjectListingStatus::Available->value;
    }

    /**
     * Compute unit availability.
     * pending = only when unit has an active reservation of type under_negotiation (حجز تفاوض).
     * Otherwise: sold, reserved, or available.
     */
    protected function computeUnitAvailability(ContractUnit $unit, string $projectSalesStatus): array
    {
        $activeReservation = $unit->activeSalesReservations->first();

        // Unit has active negotiation reservation → pending (حجز تفاوض فقط)
        if ($activeReservation && $activeReservation->status === ContractUnitWorkflowStatus::UnderNegotiation->value) {
            return ['status' => ContractUnitWorkflowStatus::Pending->value, 'can_reserve' => false];
        }

        // Unit is sold
        if ($unit->status === ContractUnitWorkflowStatus::Sold->value) {
            return ['status' => ContractUnitWorkflowStatus::Sold->value, 'can_reserve' => false];
        }

        // Unit is reserved or has confirmed reservation
        if ($unit->status === ContractUnitWorkflowStatus::Reserved->value || $activeReservation) {
            return ['status' => ContractUnitWorkflowStatus::Reserved->value, 'can_reserve' => false];
        }

        // Unit is available; can_reserve only when project is ready for sales
        if ($unit->status === ContractUnitWorkflowStatus::Available->value) {
            return [
                'status' => ContractUnitWorkflowStatus::Available->value,
                'can_reserve' => $projectSalesStatus === SalesProjectListingStatus::Available->value,
            ];
        }

        return [
            'status' => $unit->status ?? ContractUnitWorkflowStatus::Available->value,
            'can_reserve' => false,
        ];
    }

    /**
     * Get available units count for a project.
     */
    protected function getAvailableUnitsCount(Contract $contract): int
    {
        return ContractUnit::where('contract_id', $contract->id)
            ->where('status', ContractUnitWorkflowStatus::Available->value)
            ->whereDoesntHave('activeSalesReservations')
            ->count();
    }

    /**
     * Get reserved units count for a project.
     */
    protected function getReservedUnitsCount(Contract $contract): int
    {
        return ContractUnit::where('contract_id', $contract->id)
            ->where(function ($query) {
                $query->where('status', ContractUnitWorkflowStatus::Reserved->value)
                    ->orWhereHas('activeSalesReservations');
            })
            ->count();
    }

    /**
     * Apply scope filter to query.
     * أي مشروع عقده completed يظهر في القائمة، بما فيها المشاريع غير المُسنَدة لفريق.
     * - sales_leader: scope 'all' = كل المشاريع (غير معينة أو معينة لي أو لفريقي); 'me' = معينة لي أو غير معينة; 'team' = معينة لفريقي أو غير معينة.
     * - sales: scope 'me' / 'team' = مشاريع الفريق أو غير المُسنَدة.
     */
    protected function applyScopeFilter($query, string $scope, User $user): void
    {
        $unassignedCondition = function ($q) {
            $q->whereDoesntHave('salesProjectAssignments', function ($aq) {
                $aq->active();
            });
        };

        if ($user->hasRole('sales_leader')) {
            if ($scope === 'all') {
                $leaderIds = collect([$user->id]);
                if ($user->team_id) {
                    $leaderIds = $leaderIds->merge(
                        User::where('team_id', $user->team_id)->where('is_manager', true)->pluck('id')
                    );
                }
                $query->where(function ($q) use ($leaderIds, $unassignedCondition) {
                    $q->where($unassignedCondition)
                        ->orWhereHas('salesProjectAssignments', function ($aq) use ($leaderIds) {
                            $aq->whereIn('leader_id', $leaderIds)->active();
                        });
                });
                return;
            }
            if ($scope === 'me') {
                $query->where(function ($q) use ($user, $unassignedCondition) {
                    $q->where($unassignedCondition)
                        ->orWhereHas('salesProjectAssignments', function ($aq) use ($user) {
                            $aq->where('leader_id', $user->id)->active();
                        });
                });
                return;
            }
            if ($scope === 'team') {
                if ($user->team_id) {
                    $teamLeaderIds = User::where('team_id', $user->team_id)
                        ->where('is_manager', true)
                        ->pluck('id');
                    $query->where(function ($q) use ($teamLeaderIds, $unassignedCondition) {
                        $q->where($unassignedCondition)
                            ->orWhereHas('salesProjectAssignments', function ($aq) use ($teamLeaderIds) {
                                $aq->whereIn('leader_id', $teamLeaderIds)->active();
                            });
                    });
                } else {
                    $query->where(function ($q) use ($user, $unassignedCondition) {
                        $q->where($unassignedCondition)
                            ->orWhereHas('salesProjectAssignments', function ($aq) use ($user) {
                                $aq->where('leader_id', $user->id)->active();
                            });
                    });
                }
                return;
            }
            return;
        }

        // For other roles (e.g. sales staff): include unassigned so all completed contracts appear everywhere
        if ($scope === 'me') {
            if ($user->isSalesLeader()) {
                $query->where(function ($q) use ($user, $unassignedCondition) {
                    $q->where($unassignedCondition)
                        ->orWhereHas('salesProjectAssignments', function ($aq) use ($user) {
                            $aq->where('leader_id', $user->id)->active();
                        });
                });
            } else {
                if ($user->team_id) {
                    $teamLeaderIds = User::where('team_id', $user->team_id)
                        ->where('is_manager', true)
                        ->pluck('id');
                    $query->where(function ($q) use ($teamLeaderIds, $unassignedCondition) {
                        $q->where($unassignedCondition)
                            ->orWhereHas('salesProjectAssignments', function ($aq) use ($teamLeaderIds) {
                                $aq->whereIn('leader_id', $teamLeaderIds)->active();
                            });
                    });
                } else {
                    $query->where($unassignedCondition);
                }
            }
        } elseif ($scope === 'team' && $user->team_id) {
            $teamLeaderIds = User::where('team_id', $user->team_id)
                ->where('is_manager', true)
                ->pluck('id');
            $query->where(function ($q) use ($teamLeaderIds, $unassignedCondition) {
                $q->where($unassignedCondition)
                    ->orWhereHas('salesProjectAssignments', function ($aq) use ($teamLeaderIds) {
                        $aq->whereIn('leader_id', $teamLeaderIds)->active();
                    });
            });
        }
        // scope 'all' for non-leader: no extra filter (all completed projects already in base query)
    }

    /**
     * Check if the user can access a contract (project) for viewing details or units.
     * Admin: always. Any completed contract: any sales or sales_leader can access (no assignment condition).
     */
    public function userCanAccessContract(User $user, int $contractId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        $contract = Contract::find($contractId);
        if (!$contract) {
            return false;
        }
        // Sales leaders + sales staff can access completed contracts.
        if ($contract->status === ContractWorkflowStatus::Completed->value && $user->type === 'sales') {
            return true;
        }
        return false;
    }

    /**
     * Get team projects for a leader from PM team linkage only.
     */
    public function getTeamProjects(User $leader): \Illuminate\Database\Eloquent\Collection
    {
        return $this->baseTeamProjectsQuery($leader)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get team projects for leader (paginated) from PM team linkage only.
     *
     * @param User $leader
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listTeamProjectsPaginated(User $leader, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseTeamProjectsQuery($leader)
            ->orderBy('created_at', 'desc');

        $projects = $query->paginate($perPage);

        $projects->getCollection()->each(fn (Contract $c) => $this->applySalesListingComputedFields($c));

        return $projects;
    }

    /**
     * Get team members for a leader (same team_id).
     */
    public function getTeamMembers(User $leader): \Illuminate\Database\Eloquent\Collection
    {
        if (!$leader->team_id) {
            return new \Illuminate\Database\Eloquent\Collection([]);
        }
        return User::where('team_id', $leader->team_id)
            ->where('type', 'sales')
            ->where('id', '!=', $leader->id)
            ->get();
    }

    /**
     * Alias for getProjects (used by controller).
     */
    public function listProjects(array $filters, User $user): LengthAwarePaginator
    {
        return $this->getProjects($filters, $user);
    }

    /**
     * Alias for getProjectUnits (used by controller).
     */
    public function listUnits(int $contractId, array $filters): LengthAwarePaginator
    {
        return $this->getProjectUnits($contractId, $filters);
    }

    /**
     * Get team projects for leader (returns collection for resource).
     */
    public function listTeamProjects(User $leader): \Illuminate\Database\Eloquent\Collection
    {
        $projects = $this->getTeamProjects($leader);

        $projects->each(fn (Contract $c) => $this->applySalesListingComputedFields($c));

        return $projects;
    }

    /**
     * Base query for projects linked to the leader team from project management.
     */
    protected function baseTeamProjectsQuery(User $leader): Builder
    {
        $query = Contract::query()
            ->where('status', ContractWorkflowStatus::Completed->value)
            ->with([
                'contractUnits',
                'salesProjectAssignments.leader',
                'user',
                'city',
                'district',
            ]);

        if (!$leader->team_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereHas('teams', function (Builder $teams) use ($leader) {
                $teams->where('teams.id', $leader->team_id);
            })
            ->with([
                'teams' => fn ($teams) => $teams->where('teams.id', $leader->team_id),
            ]);
    }

    /**
     * True when PM linked the contract to the leader's team (contract_team), same rule as sales targets.
     *
     * FK chain: contracts ← contract_team → teams; sales_project_assignments references contracts + users.
     * See migrations: 2026_01_21_000004_create_contract_team_table, 2026_01_26_100005_create_sales_project_assignments_table.
     */
    protected function contractHasTeamForLeader(User $leader, int $contractId): bool
    {
        if (!$leader->team_id) {
            return false;
        }

        return Contract::query()
            ->whereKey($contractId)
            ->where('status', ContractWorkflowStatus::Completed->value)
            ->whereHas('teams', function (Builder $teams) use ($leader) {
                $teams->where('teams.id', $leader->team_id);
            })
            ->exists();
    }

    /**
     * Assign project to a sales team by team code (resolves the team's sales leader internally).
     */
    public function assignProjectToTeamByCode(string $teamCode, int $contractId, int $assignerId, ?string $startDate = null, ?string $endDate = null): \App\Models\SalesProjectAssignment
    {
        $normalized = trim($teamCode);
        $team = Team::query()
            ->whereRaw('LOWER(code) = ?', [strtolower($normalized)])
            ->first();

        if (!$team) {
            throw ValidationException::withMessages([
                'team_code' => [__('The selected team code is invalid.')],
            ]);
        }

        $leader = $this->resolveSalesLeaderForTeam($team);

        return $this->assignProjectToLeader($leader->id, $contractId, $assignerId, $startDate, $endDate);
    }

    /**
     * Pick one sales manager for the team (used when assigning by team_code).
     */
    protected function resolveSalesLeaderForTeam(Team $team): User
    {
        $leader = User::query()
            ->where('team_id', $team->id)
            ->where('type', 'sales')
            ->where('is_manager', true)
            ->orderBy('id')
            ->first();

        if (!$leader) {
            throw ValidationException::withMessages([
                'team_code' => [__('No sales leader found for this team.')],
            ]);
        }

        return $leader;
    }

    /**
     * Assign a project to a leader with optional date range.
     */
    public function assignProjectToLeader(int $leaderId, int $contractId, int $assignerId, ?string $startDate = null, ?string $endDate = null): \App\Models\SalesProjectAssignment
    {
        $leader = User::findOrFail($leaderId);
        $contract = Contract::findOrFail($contractId);

        if ($contract->status !== ContractWorkflowStatus::Completed->value) {
            throw ValidationException::withMessages([
                'contract_id' => [__('The contract must be completed before assigning a sales leader.')],
            ]);
        }

        if (!$leader->team_id) {
            throw ValidationException::withMessages([
                'leader_id' => [__('The sales leader must belong to a team.')],
            ]);
        }

        if (!$this->contractHasTeamForLeader($leader, $contract->id)) {
            throw ValidationException::withMessages([
                'contract_id' => [__('The contract must be assigned to the leader team by project management (contract_team).')],
            ]);
        }

        // Validate date range
        if ($startDate && $endDate && $startDate > $endDate) {
            throw new \Exception('تاريخ البداية يجب أن يكون قبل تاريخ النهاية');
        }

        // Create a temporary assignment object to check for overlaps
        $newAssignment = new \App\Models\SalesProjectAssignment([
            'leader_id' => $leaderId,
            'contract_id' => $contractId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Check for overlapping assignments for the same leader
        $existingAssignments = \App\Models\SalesProjectAssignment::where('leader_id', $leaderId)
            ->where('id', '!=', $newAssignment->id ?? 0)
            ->get();

        foreach ($existingAssignments as $existing) {
            if ($newAssignment->overlapsWith($existing)) {
                throw new \Exception('يوجد تعيين نشط آخر للمسوق في نفس الفترة الزمنية');
            }
        }

        // Create the assignment
        return \App\Models\SalesProjectAssignment::create([
            'leader_id' => $leaderId,
            'contract_id' => $contractId,
            'assigned_by' => $assignerId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Get active assignment for a leader on a specific date.
     */
    public function getActiveAssignment(int $leaderId, ?string $date = null): ?\App\Models\SalesProjectAssignment
    {
        return \App\Models\SalesProjectAssignment::where('leader_id', $leaderId)
            ->activeOnDate($date)
            ->first();
    }

    /**
     * Get project remaining days from contract.
     */
    public function getProjectRemainingDays(Contract $contract): ?int
    {
        $contractInfo = $contract->info;

        if (!$contractInfo || !$contractInfo->agreement_duration_days) {
            return null;
        }

        // Use created_at as start date
        $startDate = $contractInfo->created_at;
        $durationDays = $contractInfo->agreement_duration_days;
        $endDate = $startDate->copy()->addDays($durationDays);

        $remainingDays = (int) now()->diffInDays($endDate, false);

        // Return null if expired (negative days)
        return $remainingDays >= 0 ? $remainingDays : null;
    }

    /**
     * Count projects currently under marketing (all completed with units and priced, no scope/assignment).
     */
    public function countProjectsUnderMarketing(string $scope, User $user): int
    {
        return Contract::query()
            ->where('status', ContractWorkflowStatus::Completed->value)
            ->whereHas('contractUnits')
            ->whereDoesntHave('contractUnits', function (Builder $q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            })
            ->count();
    }
}
