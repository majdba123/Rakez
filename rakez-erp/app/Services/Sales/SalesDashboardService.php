<?php

namespace App\Services\Sales;

use App\Models\SalesReservation;
use App\Models\ContractUnit;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalesDashboardService
{
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
        $reservedUnits = $reservedUnitsQuery->whereIn('status', ['under_negotiation', 'confirmed'])
            ->distinct('contract_unit_id')
            ->count('contract_unit_id');

        // Available units: units with status available AND no active reservation
        $availableUnits = $unitsQuery->where('status', 'available')
            ->whereDoesntHave('activeSalesReservations')
            ->count();

        // Projects under marketing: contracts with status ready
        $projectsUnderMarketing = $contractsQuery->where('status', 'ready')->count();

        // Confirmed and negotiation counts
        $confirmedQuery = clone $reservationsQuery;
        $confirmedCount = $confirmedQuery->where('status', 'confirmed')->count();

        $negotiationQuery = clone $reservationsQuery;
        $negotiationCount = $negotiationQuery->where('status', 'under_negotiation')->count();

        // Calculate percentage
        $total = $confirmedCount + $negotiationCount;
        $percentConfirmed = $total > 0 ? round(($confirmedCount / $total) * 100, 2) : 0;

        return [
            'reserved_units' => $reservedUnits,
            'available_units' => $availableUnits,
            'projects_under_marketing' => $projectsUnderMarketing,
            'confirmed_reservations' => $confirmedCount,
            'negotiation_reservations' => $negotiationCount,
            'percent_confirmed' => $percentConfirmed,
            'total_reservations' => $total,
            'negotiation_ratio' => $total > 0 ? round(($negotiationCount / $total) * 100, 2) : 0,
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
        } elseif ($scope === 'team' && $user->team) {
            $teamLeaderIds = User::where('team', $user->team)
                ->where('is_manager', true)
                ->pluck('id');
            $query->whereHas('salesProjectAssignments', function ($q) use ($teamLeaderIds) {
                $q->whereIn('leader_id', $teamLeaderIds);
            });
        }
    }
}
