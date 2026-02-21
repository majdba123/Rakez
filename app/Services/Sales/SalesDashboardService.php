<?php

namespace App\Services\Sales;

use App\Constants\ReservationStatus;
use App\Models\Commission;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\User;

class SalesDashboardService
{
    public function __construct(
        private SalesProjectService $salesProjectService
    ) {}

    /**
     * Get KPI statistics for sales dashboard.
     */
    public function getKPIs(string $scope, ?string $from, ?string $to, User $user): array
    {
        $reservationsQuery = SalesReservation::query();
        $unitsQuery = ContractUnit::query();
        $contractsQuery = Contract::query();

        // Apply scope filter
        $this->applyScopeFilter($reservationsQuery, $scope, $user);
        $this->applyScopeFilterToUnits($unitsQuery, $scope, $user);
        $this->applyScopeFilterToContracts($contractsQuery, $scope, $user);

        // Apply date range filter if provided
        if ($from || $to) {
            $reservationsQuery->dateRange($from, $to);
        }

        // Reserved units: units with active reservations
        $reservedUnitsQuery = clone $reservationsQuery;
        $reservedUnits = $reservedUnitsQuery->whereIn('status', ReservationStatus::active())
            ->distinct('contract_unit_id')
            ->count('contract_unit_id');

        // Available units: units with status available AND no active reservation
        $availableUnits = $unitsQuery->where('status', 'available')
            ->whereDoesntHave('activeSalesReservations')
            ->count();

        // Projects under marketing: contracts that are sales-available (priced + ready/approved)
        $projectsUnderMarketing = $this->salesProjectService->countProjectsUnderMarketing($scope, $user);

        // Confirmed and negotiation counts
        $confirmedQuery = clone $reservationsQuery;
        $confirmedCount = $confirmedQuery->where('status', ReservationStatus::CONFIRMED)->count();

        $negotiationQuery = clone $reservationsQuery;
        $negotiationCount = $negotiationQuery->where('status', ReservationStatus::UNDER_NEGOTIATION)->count();

        // Calculate percentage
        $total = $confirmedCount + $negotiationCount;
        $percentConfirmed = $total > 0 ? round(($confirmedCount / $total) * 100, 2) : 0;

        // Financial KPI set
        $soldUnitsCount = $this->getSoldUnitsCount($scope, $from, $to, $user);
        $totalReceivedDeposits = $this->getTotalReceivedDeposits($scope, $from, $to, $user);
        $totalRefundedDeposits = $this->getTotalRefundedDeposits($scope, $from, $to, $user);
        $totalReceivedProjectsValue = $this->getTotalReceivedProjectsValue($scope, $from, $to, $user);
        $totalSalesValue = $this->getTotalSalesValue($scope, $from, $to, $user);

        return [
            'kpi_version' => 'v2',
            'definitions' => [
                'projects_under_marketing' => 'Contracts with ready/approved status and all units priced',
                'percent_confirmed' => 'confirmed_reservations / (confirmed_reservations + negotiation_reservations) * 100',
                'total_received_projects_value' => 'Sum of unit prices for projects with confirmed reservations in selected scope',
            ],
            'reserved_units' => $reservedUnits,
            'available_units' => $availableUnits,
            'projects_under_marketing' => $projectsUnderMarketing,
            'confirmed_reservations' => $confirmedCount,
            'negotiation_reservations' => $negotiationCount,
            'percent_confirmed' => $percentConfirmed,
            'total_reservations' => $total,
            'negotiation_ratio' => $total > 0 ? round(($negotiationCount / $total) * 100, 2) : 0,
            'sold_units_count' => $soldUnitsCount,
            'total_received_deposits' => $totalReceivedDeposits,
            'total_refunded_deposits' => $totalRefundedDeposits,
            'total_received_projects_value' => $totalReceivedProjectsValue,
            'total_sales_value' => $totalSalesValue,
        ];
    }

    /**
     * Apply scope filter to reservations query.
     */
    protected function applyScopeFilter($query, string $scope, User $user): void
    {
        if ($scope === 'me') {
            $query->where('marketing_employee_id', $user->id);
        } elseif ($scope === 'team' && $user->team) {
            $teamMemberIds = User::where('team', $user->team)->pluck('id');
            $query->whereIn('marketing_employee_id', $teamMemberIds);
        }
        // 'all' scope has no filter (admin view)
    }

    /**
     * Apply scope filter to units query.
     */
    protected function applyScopeFilterToUnits($query, string $scope, User $user): void
    {
        if ($scope === 'me') {
            $query->whereHas('salesReservations', function ($q) use ($user) {
                $q->where('marketing_employee_id', $user->id);
            });
        } elseif ($scope === 'team' && $user->team) {
            $teamMemberIds = User::where('team', $user->team)->pluck('id');
            $query->whereHas('salesReservations', function ($q) use ($teamMemberIds) {
                $q->whereIn('marketing_employee_id', $teamMemberIds);
            });
        }
    }

    /**
     * Apply scope filter to contracts query.
     */
    protected function applyScopeFilterToContracts($query, string $scope, User $user): void
    {
        if ($scope === 'me' && $user->isSalesLeader()) {
            $query->whereHas('salesProjectAssignments', function ($q) use ($user) {
                $q->where('leader_id', $user->id);
            });
        } elseif ($scope === 'me') {
            $query->whereHas('salesReservations', function ($q) use ($user) {
                $q->where('marketing_employee_id', $user->id);
            });
        } elseif ($scope === 'team' && $user->team) {
            $teamLeaderIds = User::where('team', $user->team)
                ->where('is_manager', true)
                ->pluck('id');
            $query->whereHas('salesProjectAssignments', function ($q) use ($teamLeaderIds) {
                $q->whereIn('leader_id', $teamLeaderIds);
            });
        }
    }

    protected function getSoldUnitsCount(string $scope, ?string $from, ?string $to, User $user): int
    {
        $query = ContractUnit::query()->where('status', 'sold');

        $query->whereHas('salesReservations', function ($q) use ($scope, $from, $to, $user) {
            $q->where('status', ReservationStatus::CONFIRMED);
            $this->applyScopeFilter($q, $scope, $user);
            if ($from || $to) {
                $q->dateRange($from, $to);
            }
        });

        return $query->count();
    }

    protected function getTotalReceivedDeposits(string $scope, ?string $from, ?string $to, User $user): float
    {
        $query = Deposit::query()->whereIn('status', \App\Constants\DepositStatus::receivedOrConfirmed());
        $this->applyScopeFilterToDeposits($query, $scope, $user);
        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    protected function getTotalRefundedDeposits(string $scope, ?string $from, ?string $to, User $user): float
    {
        $query = Deposit::query()->where('status', 'refunded');
        $this->applyScopeFilterToDeposits($query, $scope, $user);
        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    protected function getTotalReceivedProjectsValue(string $scope, ?string $from, ?string $to, User $user): float
    {
        $contractsQuery = Contract::query()
            ->whereHas('salesReservations', function ($q) use ($scope, $from, $to, $user) {
                $q->where('status', ReservationStatus::CONFIRMED);
                $this->applyScopeFilter($q, $scope, $user);
                if ($from || $to) {
                    $q->dateRange($from, $to);
                }
            })
            ->with('secondPartyData.contractUnits');

        return (float) $contractsQuery->get()->sum(function (Contract $contract) {
            return $contract->secondPartyData?->contractUnits->sum('price') ?? 0;
        });
    }

    protected function getTotalSalesValue(string $scope, ?string $from, ?string $to, User $user): float
    {
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
        if ($scope === 'me') {
            $query->whereHas('salesReservation', function ($q) use ($user) {
                $q->where('marketing_employee_id', $user->id);
            });
        } elseif ($scope === 'team' && $user->team) {
            $teamMemberIds = User::where('team', $user->team)->pluck('id');
            $query->whereHas('salesReservation', function ($q) use ($teamMemberIds) {
                $q->whereIn('marketing_employee_id', $teamMemberIds);
            });
        }
    }
}
