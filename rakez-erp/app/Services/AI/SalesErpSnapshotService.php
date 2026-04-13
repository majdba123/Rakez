<?php

namespace App\Services\AI;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\SalesReservationAction;
use Illuminate\Support\Carbon;

/**
 * Read-only queries for sales AI tools. No authorization — callers must gate permissions.
 */
class SalesErpSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function reservationMomentum(?Carbon $from, Carbon $to, ?int $contractId): array
    {
        $query = SalesReservation::query();
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        $query->where('created_at', '<=', $to);
        if ($contractId) {
            $query->where('contract_id', $contractId);
        }

        $total = (clone $query)->count();
        $confirmed = (clone $query)->where('status', 'confirmed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $cancelled = (clone $query)->where('status', 'cancelled')->count();

        return [
            'period' => [
                'from' => $from?->toDateString() ?? 'all_time',
                'to' => $to->toDateString(),
            ],
            'contract_id' => $contractId,
            'total_reservations' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'confirmation_rate_percent' => $total > 0 ? round(($confirmed / $total) * 100, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projectInventorySnapshot(int $contractId): array
    {
        $rows = ContractUnit::query()
            ->where('contract_id', $contractId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $total = array_sum($rows);

        return [
            'contract_id' => $contractId,
            'units_total' => $total,
            'units_by_status' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projectPricingSnapshot(int $contractId): array
    {
        $stats = ContractUnit::query()
            ->where('contract_id', $contractId)
            ->whereNotNull('price')
            ->selectRaw('COUNT(*) as n, MIN(price) as min_p, MAX(price) as max_p, AVG(price) as avg_p')
            ->first();

        $n = (int) ($stats->n ?? 0);

        return [
            'contract_id' => $contractId,
            'priced_units' => $n,
            'min_price' => $n > 0 ? round((float) $stats->min_p, 2) : null,
            'max_price' => $n > 0 ? round((float) $stats->max_p, 2) : null,
            'avg_price' => $n > 0 ? round((float) $stats->avg_p, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function projectReadinessFacts(Contract $contract): array
    {
        return [
            'contract_id' => $contract->id,
            'project_name' => $contract->project_name,
            'status' => $contract->status,
            'is_off_plan' => (bool) $contract->is_off_plan,
            'is_closed' => (bool) $contract->is_closed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reservationActivityForReservation(int $salesReservationId): array
    {
        $byType = SalesReservationAction::query()
            ->where('sales_reservation_id', $salesReservationId)
            ->selectRaw('action_type, COUNT(*) as c')
            ->groupBy('action_type')
            ->pluck('c', 'action_type')
            ->toArray();

        $latest = SalesReservationAction::query()
            ->where('sales_reservation_id', $salesReservationId)
            ->orderByDesc('created_at')
            ->first();

        return [
            'sales_reservation_id' => $salesReservationId,
            'actions_total' => array_sum($byType),
            'actions_by_type' => $byType,
            'latest_action_at' => $latest?->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reservationActivityForContract(int $contractId): array
    {
        $reservationIds = SalesReservation::query()
            ->where('contract_id', $contractId)
            ->pluck('id');

        if ($reservationIds->isEmpty()) {
            return [
                'contract_id' => $contractId,
                'actions_total' => 0,
                'actions_by_type' => [],
                'reservations_with_actions' => 0,
            ];
        }

        $byType = SalesReservationAction::query()
            ->whereIn('sales_reservation_id', $reservationIds)
            ->selectRaw('action_type, COUNT(*) as c')
            ->groupBy('action_type')
            ->pluck('c', 'action_type')
            ->toArray();

        $withActions = SalesReservationAction::query()
            ->whereIn('sales_reservation_id', $reservationIds)
            ->select('sales_reservation_id')
            ->groupBy('sales_reservation_id')
            ->get()
            ->count();

        return [
            'contract_id' => $contractId,
            'actions_total' => array_sum($byType),
            'actions_by_type' => $byType,
            'reservations_with_actions' => $withActions,
        ];
    }
}
