<?php

namespace App\Services\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesProjectService
{
    public const SALES_STATUS_PENDING = 'pending';
    public const SALES_STATUS_AVAILABLE = 'available';
    /**
     * Get projects with sales status computation.
     */
    public function getProjects(array $filters, User $user): LengthAwarePaginator
    {
        // عرض عقود مكتملة ضمنياً فقط في قائمة مشاريع السيلز
        $query = Contract::with([
            'secondPartyData.contractUnits',
            'montageDepartment',
            'salesProjectAssignments.leader',
            'user'
        ])->where('status', 'completed');

        // Apply filters
        if (!empty($filters['q'])) {
            $query->where('project_name', 'like', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (!empty($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        // Sales user sees all completed contracts (no assignment/scope filter).
        // applyScopeFilter intentionally not applied so every sales user sees the same list.

        $perPage = $filters['per_page'] ?? 15;
        $contracts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Compute sales status for each project
        $contracts->getCollection()->transform(function ($contract) {
            $contract->sales_status = $this->computeProjectSalesStatus($contract);
            $contract->total_units = $contract->secondPartyData ? $contract->secondPartyData->contractUnits()->count() : 0;
            $contract->available_units = $this->getAvailableUnitsCount($contract);
            $contract->reserved_units = $this->getReservedUnitsCount($contract);
            $contract->remaining_days = $this->getProjectRemainingDays($contract);
            return $contract;
        });

        // Apply status filter after computation (in-memory since sales_status is computed)
        if (!empty($filters['status'])) {
            $filtered = $contracts->getCollection()->filter(function ($contract) use ($filters) {
                return $contract->sales_status === $filters['status'];
            })->values();

            return new LengthAwarePaginator(
                $filtered,
                $filtered->count(),
                $contracts->perPage(),
                $contracts->currentPage(),
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        return $contracts;
    }

    /**
     * Get project details by ID.
     */
    public function getProjectById(int $contractId): Contract
    {
        $contract = Contract::with([
            'secondPartyData.contractUnits',
            'montageDepartment',
            'info',
            'salesProjectAssignments.leader',
            'user'
        ])->findOrFail($contractId);

        $contract->sales_status = $this->computeProjectSalesStatus($contract);
        $contract->total_units = $contract->secondPartyData ? $contract->secondPartyData->contractUnits()->count() : 0;
        $contract->available_units = $this->getAvailableUnitsCount($contract);
        $contract->reserved_units = $this->getReservedUnitsCount($contract);
        $contract->remaining_days = $this->getProjectRemainingDays($contract);

        return $contract;
    }

    /**
     * Get units for a project with computed availability.
     */
    public function getProjectUnits(int $contractId, array $filters): LengthAwarePaginator
    {
        $contract = Contract::with('secondPartyData')->findOrFail($contractId);
        
        if (!$contract->secondPartyData) {
            throw new \Exception('Project has no units data');
        }

        $query = ContractUnit::where('second_party_data_id', $contract->secondPartyData->id)
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

        $perPage = $filters['per_page'] ?? 15;
        $units = $query->orderBy('unit_number')->paginate($perPage);

        // Compute availability for each unit
        $projectSalesStatus = $this->computeProjectSalesStatus($contract);
        
        $units->getCollection()->transform(function ($unit) use ($projectSalesStatus) {
            $availability = $this->computeUnitAvailability($unit, $projectSalesStatus);
            $unit->computed_availability = $availability['status'];
            $unit->can_reserve = $availability['can_reserve'];
            $unit->active_reservation = $unit->activeSalesReservations->first();
            return $unit;
        });

        // Apply status filter after computation (in-memory since computed_availability is dynamic)
        if (!empty($filters['status'])) {
            $filtered = $units->getCollection()->filter(function ($unit) use ($filters) {
                return $unit->computed_availability === $filters['status'];
            })->values();

            return new LengthAwarePaginator(
                $filtered,
                $filtered->count(),
                $units->perPage(),
                $units->currentPage(),
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

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
        if ($contract->status !== 'completed') {
            return self::SALES_STATUS_PENDING;
        }

        // Check if SecondPartyData exists
        $secondPartyData = $contract->secondPartyData;
        if (!$secondPartyData) {
            // Force load relation if not loaded
            $contract->load('secondPartyData.contractUnits');
            $secondPartyData = $contract->secondPartyData;
            if (!$secondPartyData) {
                return self::SALES_STATUS_PENDING;
            }
        }

        // Check if all units have price > 0
        // Use the relationship directly to avoid collection caching issues in tests
        $unitsQuery = $secondPartyData->contractUnits();
        
        // If unitsQuery is empty, try to refresh relation
        if ($unitsQuery->count() === 0) {
            $secondPartyData->unsetRelation('contractUnits');
            $unitsQuery = $secondPartyData->contractUnits();
            if ($unitsQuery->count() === 0) {
                // If still 0, maybe it's not saved yet? (though factory should save it)
                // In tests, sometimes the relationship needs to be completely re-fetched
                $freshSecondPartyData = \App\Models\SecondPartyData::with('contractUnits')->find($secondPartyData->id);
                if (!$freshSecondPartyData || $freshSecondPartyData->contractUnits()->count() === 0) {
                    // One last try: check if it's a new instance not yet in DB
                    if ($secondPartyData->contractUnits->isNotEmpty()) {
                        $unitsQuery = $secondPartyData->contractUnits;
                    } else {
                        return self::SALES_STATUS_PENDING;
                    }
                } else {
                    $unitsQuery = $freshSecondPartyData->contractUnits();
                }
            }
        }

        if ($unitsQuery instanceof \Illuminate\Database\Eloquent\Collection) {
            $hasUnpricedUnits = $unitsQuery->some(function ($unit) {
                return is_null($unit->price) || $unit->price <= 0;
            });
        } else {
            $hasUnpricedUnits = $unitsQuery->where(function ($query) {
                $query->whereNull('price')
                    ->orWhere('price', '<=', 0);
            })->exists();
        }

        if ($hasUnpricedUnits) {
            return self::SALES_STATUS_PENDING;
        }

        return self::SALES_STATUS_AVAILABLE;
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
        if ($activeReservation && $activeReservation->status === 'under_negotiation') {
            return ['status' => 'pending', 'can_reserve' => false];
        }

        // Unit is sold
        if ($unit->status === 'sold') {
            return ['status' => 'sold', 'can_reserve' => false];
        }

        // Unit is reserved or has confirmed reservation
        if ($unit->status === 'reserved' || $activeReservation) {
            return ['status' => 'reserved', 'can_reserve' => false];
        }

        // Unit is available; can_reserve only when project is ready for sales
        if ($unit->status === 'available') {
            return [
                'status' => 'available',
                'can_reserve' => $projectSalesStatus === self::SALES_STATUS_AVAILABLE,
            ];
        }

        return ['status' => $unit->status ?? 'available', 'can_reserve' => false];
    }

    /**
     * Get available units count for a project.
     */
    protected function getAvailableUnitsCount(Contract $contract): int
    {
        if (!$contract->secondPartyData) {
            return 0;
        }

        return ContractUnit::where('second_party_data_id', $contract->secondPartyData->id)
            ->where('status', 'available')
            ->whereDoesntHave('activeSalesReservations')
            ->count();
    }

    /**
     * Get reserved units count for a project.
     */
    protected function getReservedUnitsCount(Contract $contract): int
    {
        if (!$contract->secondPartyData) {
            return 0;
        }

        return ContractUnit::where('second_party_data_id', $contract->secondPartyData->id)
            ->where(function ($query) {
                $query->where('status', 'reserved')
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
        if ($contract->status === 'completed' && ($user->hasRole('sales_leader') || $user->hasRole('sales'))) {
            return true;
        }
        return false;
    }

    /**
     * Get team projects for a leader (all completed contracts, no assignment condition).
     */
    public function getTeamProjects(User $leader): \Illuminate\Database\Eloquent\Collection
    {
        return Contract::where('status', 'completed')
            ->with(['secondPartyData.contractUnits', 'salesProjectAssignments.leader', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get team projects for leader (paginated). All completed contracts, no assignment condition.
     *
     * @param User $leader
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listTeamProjectsPaginated(User $leader, int $perPage = 15): LengthAwarePaginator
    {
        $query = Contract::where('status', 'completed')
            ->with(['secondPartyData.contractUnits', 'salesProjectAssignments.leader', 'user'])
            ->orderBy('created_at', 'desc');

        $projects = $query->paginate($perPage);

        // Compute sales status for each project
        $projects->getCollection()->transform(function ($contract) {
            $contract->sales_status = $this->computeProjectSalesStatus($contract);
            $contract->total_units = $contract->secondPartyData ? $contract->secondPartyData->contractUnits()->count() : 0;
            $contract->available_units = $this->getAvailableUnitsCount($contract);
            $contract->reserved_units = $this->getReservedUnitsCount($contract);
            $contract->remaining_days = $this->getProjectRemainingDays($contract);
            return $contract;
        });

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
        
        // Compute sales status for each project
        $projects->transform(function ($contract) {
            $contract->sales_status = $this->computeProjectSalesStatus($contract);
            $contract->total_units = $contract->secondPartyData ? $contract->secondPartyData->contractUnits()->count() : 0;
            $contract->available_units = $this->getAvailableUnitsCount($contract);
            $contract->reserved_units = $this->getReservedUnitsCount($contract);
            $contract->remaining_days = $this->getProjectRemainingDays($contract);
            return $contract;
        });

        return $projects;
    }

    /**
     * Assign a project to a leader with optional date range.
     */
    public function assignProjectToLeader(int $leaderId, int $contractId, int $assignerId, ?string $startDate = null, ?string $endDate = null): \App\Models\SalesProjectAssignment
    {
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
            ->where('status', 'completed')
            ->whereHas('secondPartyData.contractUnits')
            ->whereDoesntHave('secondPartyData.contractUnits', function (Builder $q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            })
            ->count();
    }
}
