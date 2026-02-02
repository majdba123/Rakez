<?php

namespace App\Services\Sales;

use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\Contract;
use App\Models\Commission;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    /**
     * Get dashboard KPI statistics.
     */
    public function getDashboardKPIs(?string $from = null, ?string $to = null): array
    {
        return [
            'units_sold' => $this->getUnitsSold($from, $to),
            'total_received_deposits' => $this->getTotalReceivedDeposits($from, $to),
            'total_refunded_deposits' => $this->getTotalRefundedDeposits($from, $to),
            'total_projects_value' => $this->getTotalProjectsValue($from, $to),
            'total_sales_value' => $this->getTotalSalesValue($from, $to),
            'total_commissions' => $this->getTotalCommissions($from, $to),
            'pending_commissions' => $this->getPendingCommissions($from, $to),
        ];
    }

    /**
     * Get number of units sold.
     */
    public function getUnitsSold(?string $from = null, ?string $to = null): int
    {
        $query = ContractUnit::where('status', 'sold');

        if ($from || $to) {
            $query->whereHas('salesReservations', function ($q) use ($from, $to) {
                $q->where('status', 'confirmed');
                if ($from) {
                    $q->whereDate('confirmed_at', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('confirmed_at', '<=', $to);
                }
            });
        }

        return $query->count();
    }

    /**
     * Get total received deposits amount.
     */
    public function getTotalReceivedDeposits(?string $from = null, ?string $to = null): float
    {
        $query = Deposit::whereIn('status', ['received', 'confirmed']);

        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total refunded deposits amount.
     */
    public function getTotalRefundedDeposits(?string $from = null, ?string $to = null): float
    {
        $query = Deposit::where('status', 'refunded');

        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total value of projects (contracts).
     */
    public function getTotalProjectsValue(?string $from = null, ?string $to = null): float
    {
        $query = Contract::query();

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        // Sum up all unit prices for each contract
        return (float) $query->with('secondPartyData.contractUnits')
            ->get()
            ->sum(function ($contract) {
                return $contract->secondPartyData?->contractUnits->sum('price') ?? 0;
            });
    }

    /**
     * Get total sales value based on final selling price.
     */
    public function getTotalSalesValue(?string $from = null, ?string $to = null): float
    {
        $query = Commission::query();

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        return (float) $query->sum('final_selling_price');
    }

    /**
     * Get total commissions amount.
     */
    public function getTotalCommissions(?string $from = null, ?string $to = null): float
    {
        $query = Commission::query();

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        return (float) $query->sum('net_amount');
    }

    /**
     * Get pending commissions amount.
     */
    public function getPendingCommissions(?string $from = null, ?string $to = null): float
    {
        $query = Commission::pending();

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        return (float) $query->sum('net_amount');
    }

    /**
     * Get sold units with details for listing.
     */
    public function getSoldUnits(?string $from = null, ?string $to = null, ?int $perPage = 15)
    {
        $query = ContractUnit::where('status', 'sold')
            ->with([
                'secondPartyData.contract',
                'salesReservations' => function ($q) {
                    $q->where('status', 'confirmed')->latest();
                },
                'commission.distributions.user',
            ]);

        if ($from || $to) {
            $query->whereHas('salesReservations', function ($q) use ($from, $to) {
                $q->where('status', 'confirmed');
                if ($from) {
                    $q->whereDate('confirmed_at', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('confirmed_at', '<=', $to);
                }
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get deposit statistics by project.
     */
    public function getDepositStatsByProject(int $contractId): array
    {
        return [
            'total_received' => Deposit::totalReceivedForProject($contractId),
            'total_refunded' => Deposit::totalRefundedForProject($contractId),
            'net_deposits' => Deposit::totalReceivedForProject($contractId) - Deposit::totalRefundedForProject($contractId),
            'count_received' => Deposit::where('contract_id', $contractId)
                ->whereIn('status', ['received', 'confirmed'])
                ->count(),
            'count_refunded' => Deposit::where('contract_id', $contractId)
                ->where('status', 'refunded')
                ->count(),
        ];
    }

    /**
     * Get commission statistics by employee.
     */
    public function getCommissionStatsByEmployee(int $userId, ?string $from = null, ?string $to = null): array
    {
        $query = DB::table('commission_distributions')
            ->where('user_id', $userId)
            ->where('status', 'approved');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        $totalAmount = (float) $query->sum('amount');
        $count = $query->count();

        return [
            'total_commission' => $totalAmount,
            'commission_count' => $count,
            'average_commission' => $count > 0 ? $totalAmount / $count : 0,
        ];
    }

    /**
     * Get monthly commission report for all employees.
     */
    public function getMonthlyCommissionReport(int $year, int $month)
    {
        $startDate = date('Y-m-01', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t', strtotime("$year-$month-01"));

        return DB::table('commission_distributions')
            ->join('users', 'commission_distributions.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.salary',
                'users.type as job_title',
                DB::raw('SUM(commission_distributions.amount) as total_commission'),
                DB::raw('COUNT(commission_distributions.id) as commission_count')
            )
            ->where('commission_distributions.status', 'approved')
            ->whereDate('commission_distributions.created_at', '>=', $startDate)
            ->whereDate('commission_distributions.created_at', '<=', $endDate)
            ->groupBy('users.id', 'users.name', 'users.salary', 'users.type')
            ->get();
    }
}
