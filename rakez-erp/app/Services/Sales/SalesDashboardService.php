<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use App\Models\ContractUnit;
use App\Models\Contract;
use App\Models\Deposit;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Support\Collection;

class SalesDashboardService
{
    public function __construct(
        private SalesProjectService $salesProjectService
    ) {}

    /**
     * Normalize scope from query string (casing / whitespace). Invalid values use $fallback (e.g. role default).
     */
    public function normalizeDashboardScope(string $scope, ?string $fallback = null): string
    {
        $s = strtolower(trim($scope));
        if ($s == 'me' || $s == 'team' || $s == 'all') {
            return $s;
        }

        return $fallback ?? 'me';
    }

    /**
     * IDs of users in the same HR team (empty collection if no team_id).
     */
    protected function teamMemberIds(User $user): Collection
    {
        if (! $user->team_id) {
            return collect();
        }

        return User::query()
            ->where('team_id', (int) $user->team_id)
            ->pluck('id');
    }

    /**
     * Get KPI statistics for sales dashboard.
     */
    public function getKPIs(string $scope, ?string $from, ?string $to, User $user): array
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');

        $reservationsQuery = SalesReservation::query();
        $unitsQuery = ContractUnit::query();
        $contractsQuery = Contract::query();

        $this->applyScopeFilter($reservationsQuery, $scope, $user);
        $this->applyScopeFilterToUnits($unitsQuery, $scope, $user);
        $this->applyScopeFilterToContracts($contractsQuery, $scope, $user);

        if ($from || $to) {
            $reservationsQuery->dateRange($from, $to);
        }

        $reservedUnitsQuery = clone $reservationsQuery;
        $reservedUnits = $reservedUnitsQuery->whereIn('status', ['under_negotiation', 'confirmed'])
            ->distinct('contract_unit_id')
            ->count('contract_unit_id');

        $availableUnits = $unitsQuery->where('status', 'available')
            ->whereDoesntHave('activeSalesReservations')
            ->count();

        $projectsUnderMarketing = $this->salesProjectService->countProjectsUnderMarketing($scope, $user);

        $confirmedQuery = clone $reservationsQuery;
        $confirmedCount = $confirmedQuery->where('status', 'confirmed')->count();

        $negotiationQuery = clone $reservationsQuery;
        $negotiationCount = $negotiationQuery->where('status', 'under_negotiation')->count();

        $total = $confirmedCount + $negotiationCount;
        $percentConfirmed = $total > 0 ? round(($confirmedCount / $total) * 100, 2) : 0;

        $soldUnitsCount = $this->getSoldUnitsCount($scope, $from, $to, $user);
        $totalReceivedDeposits = $this->getTotalReceivedDeposits($scope, $from, $to, $user);
        $totalRefundedDeposits = $this->getTotalRefundedDeposits($scope, $from, $to, $user);
        $totalReceivedProjectsValue = $this->getTotalReceivedProjectsValue($scope, $from, $to, $user);
        $totalSalesValue = $this->getTotalSalesValue($scope, $from, $to, $user);
        $depositsSummary = $this->getDepositsSummary($scope, $from, $to, $user);

        $negotiationRatio = $total > 0 ? round(($negotiationCount / $total) * 100, 2) : 0;

        $indicators = [
            'reserved_units' => [
                'value' => $reservedUnits,
                'label_ar' => 'عدد الوحدات المحجوزة',
            ],
            'available_units' => [
                'value' => $availableUnits,
                'label_ar' => 'عدد الوحدات المتاحة',
            ],
            'projects_under_marketing' => [
                'value' => $projectsUnderMarketing,
                'label_ar' => 'عدد المشاريع قيد التسويق',
            ],
            'confirmed_vs_negotiation' => [
                'confirmed_count' => $confirmedCount,
                'negotiation_count' => $negotiationCount,
                'percent_confirmed' => $percentConfirmed,
                'percent_negotiation' => $negotiationRatio,
                'label_ar' => 'نسبة الحجوزات المؤكدة مقابل التفاوض',
            ],
            'deposits' => [
                'total_received' => $totalReceivedDeposits,
                'total_refunded' => $totalRefundedDeposits,
                'count' => $depositsSummary['count'],
                'pending_count' => $depositsSummary['pending_count'],
                'label_ar' => 'العرابين',
            ],
        ];

        return [
            'kpi_version' => 'v2',
            'scope' => $scope,
            'definitions' => [
                'projects_under_marketing' => 'Contracts with completed status and all units priced',
                'percent_confirmed' => 'confirmed_count / (confirmed_count + negotiation_count) * 100',
                'total_received_projects_value' => 'Sum of unit prices for projects with confirmed reservations in selected scope',
                'scope_team_for_leader' => 'Team members by team_id OR reservations on contracts where user is assigned sales leader',
            ],
            'indicators' => $indicators,
            'reserved_units' => $reservedUnits,
            'available_units' => $availableUnits,
            'projects_under_marketing' => $projectsUnderMarketing,
            'confirmed_count' => $confirmedCount,
            'confirmed_reservations' => $confirmedCount,
            'negotiation_count' => $negotiationCount,
            'negotiation_reservations' => $negotiationCount,
            'percent_confirmed' => $percentConfirmed,
            'total_reservations' => $total,
            'negotiation_ratio' => $negotiationRatio,
            'sold_units_count' => $soldUnitsCount,
            'total_received_deposits' => $totalReceivedDeposits,
            'total_refunded_deposits' => $totalRefundedDeposits,
            'deposits' => $depositsSummary,
            'total_received_projects_value' => $totalReceivedProjectsValue,
            'total_revenue' => $totalSalesValue,
            'total_sales_value' => $totalSalesValue,
        ];
    }

    protected function getDepositsSummary(string $scope, ?string $from, ?string $to, User $user): array
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $baseQuery = Deposit::query();
        $this->applyScopeFilterToDeposits($baseQuery, $scope, $user);
        if ($from || $to) {
            $baseQuery->dateRange($from, $to);
        }

        $count = (clone $baseQuery)->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();

        return [
            'count' => $count,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * Reservation rows visible under scope (uses == for scope to tolerate normalized strings).
     */
    protected function applyScopeFilter($query, string $scope, User $user): void
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');

        if ($scope == 'me') {
            $query->where('marketing_employee_id', (int) $user->id);

            return;
        }

        if ($scope == 'team') {
            $this->applyTeamScopeToReservationsQuery($query, $user);

            return;
        }

        // all: no filter
    }

    /**
     * Reservation list/index visibility for sales users (single source of truth with dashboard "team" KPI scope).
     *
     * - Non-sales and admins: no narrowing here (caller may apply `mine` or leave unscoped).
     * - Regular sales: own reservations only.
     * - Sales leaders: same team as {@see applyTeamScopeToReservationsQuery} (team members + contracts they lead as assignment leader + own).
     */
    public function applyReservationListVisibility($query, User $user): void
    {
        if ($user->type !== 'sales' || $user->hasRole('admin')) {
            return;
        }

        if ($user->isSalesLeader()) {
            $this->applyTeamScopeToReservationsQuery($query, $user);

            return;
        }

        $query->where('marketing_employee_id', (int) $user->id);
    }

    /**
     * Team scope: members sharing team_id. For sales leaders, also rows on contracts they lead (اسناد مشروع).
     */
    protected function applyTeamScopeToReservationsQuery($query, User $user): void
    {
        $teamMemberIds = $this->teamMemberIds($user);

        if ($user->isSalesLeader()) {
            $query->where(function ($q) use ($teamMemberIds, $user) {
                if ($teamMemberIds->isNotEmpty()) {
                    $q->whereIn('marketing_employee_id', $teamMemberIds);
                }
                $q->orWhereHas('contract.salesProjectAssignments', function ($aq) use ($user) {
                    $aq->where('leader_id', (int) $user->id)->active();
                });
                if ($teamMemberIds->isEmpty()) {
                    $q->orWhere('marketing_employee_id', (int) $user->id);
                }
            });

            return;
        }

        if ($teamMemberIds->isNotEmpty()) {
            $query->whereIn('marketing_employee_id', $teamMemberIds);
        } else {
            $query->where('marketing_employee_id', (int) $user->id);
        }
    }

    protected function applyScopeFilterToUnits($query, string $scope, User $user): void
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');

        if ($scope == 'me') {
            $query->whereHas('salesReservations', function ($q) use ($user) {
                $q->where('marketing_employee_id', (int) $user->id);
            });

            return;
        }

        if ($scope == 'team') {
            $teamMemberIds = $this->teamMemberIds($user);

            $query->where(function ($q) use ($teamMemberIds, $user) {
                if ($teamMemberIds->isNotEmpty()) {
                    $q->whereHas('salesReservations', function ($sq) use ($teamMemberIds) {
                        $sq->whereIn('marketing_employee_id', $teamMemberIds);
                    });
                }
                if ($user->isSalesLeader()) {
                    $q->orWhereHas('salesReservations', function ($sq) use ($user) {
                        $sq->whereHas('contract.salesProjectAssignments', function ($aq) use ($user) {
                            $aq->where('leader_id', (int) $user->id)->active();
                        });
                    });
                }
                if ($teamMemberIds->isEmpty() && ! $user->isSalesLeader()) {
                    $q->whereHas('salesReservations', function ($sq) use ($user) {
                        $sq->where('marketing_employee_id', (int) $user->id);
                    });
                }
                if ($teamMemberIds->isEmpty() && $user->isSalesLeader()) {
                    $q->orWhereHas('salesReservations', function ($sq) use ($user) {
                        $sq->where('marketing_employee_id', (int) $user->id);
                    });
                }
            });

            return;
        }
    }

    protected function applyScopeFilterToContracts($query, string $scope, User $user): void
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');

        if ($scope == 'me' && $user->isSalesLeader()) {
            $query->whereHas('salesProjectAssignments', function ($q) use ($user) {
                $q->where('leader_id', (int) $user->id);
            });

            return;
        }

        if ($scope == 'me') {
            $query->whereHas('salesReservations', function ($q) use ($user) {
                $q->where('marketing_employee_id', (int) $user->id);
            });

            return;
        }

        if ($scope == 'team') {
            if ($user->team_id) {
                $teamLeaderIds = User::where('team_id', (int) $user->team_id)
                    ->where('is_manager', true)
                    ->pluck('id');
                $query->whereHas('salesProjectAssignments', function ($q) use ($teamLeaderIds) {
                    $q->whereIn('leader_id', $teamLeaderIds);
                });
            } else {
                $query->whereHas('salesProjectAssignments', function ($q) use ($user) {
                    $q->where('leader_id', (int) $user->id);
                });
            }
        }
    }

    protected function getSoldUnitsCount(string $scope, ?string $from, ?string $to, User $user): int
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $query = ContractUnit::query()->where('status', 'sold');

        $query->whereHas('salesReservations', function ($q) use ($scope, $from, $to, $user) {
            $q->where('status', 'confirmed');
            $this->applyScopeFilter($q, $scope, $user);
            if ($from || $to) {
                $q->dateRange($from, $to);
            }
        });

        return $query->count();
    }

    protected function getTotalReceivedDeposits(string $scope, ?string $from, ?string $to, User $user): float
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $query = Deposit::query()->whereIn('status', ['received', 'confirmed']);
        $this->applyScopeFilterToDeposits($query, $scope, $user);
        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    protected function getTotalRefundedDeposits(string $scope, ?string $from, ?string $to, User $user): float
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $query = Deposit::query()->where('status', 'refunded');
        $this->applyScopeFilterToDeposits($query, $scope, $user);
        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    protected function getTotalReceivedProjectsValue(string $scope, ?string $from, ?string $to, User $user): float
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $contractsQuery = Contract::query()
            ->whereHas('salesReservations', function ($q) use ($scope, $from, $to, $user) {
                $q->where('status', 'confirmed');
                $this->applyScopeFilter($q, $scope, $user);
                if ($from || $to) {
                    $q->dateRange($from, $to);
                }
            })
            ->with('contractUnits');

        return (float) $contractsQuery->get()->sum(function (Contract $contract) {
            return $contract->contractUnits->sum('price');
        });
    }

    protected function getTotalSalesValue(string $scope, ?string $from, ?string $to, User $user): float
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');
        $query = Commission::query();
        $query->whereHas('salesReservation', function ($q) use ($scope, $from, $to, $user) {
            $this->applyScopeFilter($q, $scope, $user);
            if ($from || $to) {
                $q->dateRange($from, $to);
            }
        });

        return (float) $query->sum('final_selling_price');
    }

    protected function applyScopeFilterToDeposits($query, string $scope, User $user): void
    {
        $scope = $this->normalizeDashboardScope($scope, 'me');

        if ($scope == 'me') {
            $query->whereHas('salesReservation', function ($q) use ($user) {
                $q->where('marketing_employee_id', (int) $user->id);
            });

            return;
        }

        if ($scope == 'team') {
            $query->whereHas('salesReservation', function ($q) use ($user) {
                $this->applyTeamScopeToReservationsQuery($q, $user);
            });

            return;
        }
    }
}
