<?php

namespace App\Services\Accounting;

use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\Contract;
use App\Models\Commission;
use App\Models\SalesReservation;
use Illuminate\Support\Facades\DB;

class AccountingDashboardService
{
    /**
     * Get dashboard KPI statistics.
     */
    public function getDashboardMetrics(?string $from = null, ?string $to = null): array
    {
        return [
            'units_sold' => $this->getUnitsSold($from, $to),
            'total_received_deposits' => $this->getTotalReceivedDeposits($from, $to),
            'total_refunded_deposits' => $this->getTotalRefundedDeposits($from, $to),
            'total_projects_value' => $this->getTotalProjectsValue($from, $to),
            'total_sales_value' => $this->getTotalSalesValue($from, $to),
            'total_commissions' => $this->getTotalCommissions($from, $to),
            'pending_commissions' => $this->getPendingCommissions($from, $to),
            'approved_commissions' => $this->getApprovedCommissions($from, $to),
        ];
    }

    /**
     * Get number of units sold.
     */
    public function getUnitsSold(?string $from = null, ?string $to = null): int
    {
        $query = SalesReservation::where('status', 'confirmed');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('confirmed_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('confirmed_at', '<=', $to);
            }
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
            if ($from) {
                $query->whereDate('payment_date', '>=', $from);
            }
            if ($to) {
                $query->whereDate('payment_date', '<=', $to);
            }
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
            if ($from) {
                $query->whereDate('refunded_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('refunded_at', '<=', $to);
            }
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get total value of received projects (sum of all unit prices in contracts).
     */
    public function getTotalProjectsValue(?string $from = null, ?string $to = null): float
    {
        $query = Contract::with('secondPartyData.contractUnits');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        $contracts = $query->get();
        $totalValue = 0;

        foreach ($contracts as $contract) {
            if ($contract->secondPartyData && $contract->secondPartyData->contractUnits) {
                $totalValue += $contract->secondPartyData->contractUnits->sum('price');
            }
        }

        return (float) $totalValue;
    }

    /**
     * Get total sales value (based on final selling price of confirmed reservations).
     */
    public function getTotalSalesValue(?string $from = null, ?string $to = null): float
    {
        $query = SalesReservation::where('status', 'confirmed');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('confirmed_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('confirmed_at', '<=', $to);
            }
        }

        // Sum proposed_price (final selling price)
        return (float) $query->sum('proposed_price');
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
     * Get pending commissions count.
     */
    public function getPendingCommissions(?string $from = null, ?string $to = null): int
    {
        $query = Commission::where('status', 'pending');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('created_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('created_at', '<=', $to);
            }
        }

        return $query->count();
    }

    /**
     * Get approved commissions count.
     */
    public function getApprovedCommissions(?string $from = null, ?string $to = null): int
    {
        $query = Commission::where('status', 'approved');

        if ($from || $to) {
            if ($from) {
                $query->whereDate('approved_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('approved_at', '<=', $to);
            }
        }

        return $query->count();
    }
}
