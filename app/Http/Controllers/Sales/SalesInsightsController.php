<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Services\Sales\CommissionService;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\DepositService;
use App\Services\Sales\SalesAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInsightsController extends Controller
{
    public function __construct(
        private SalesAnalyticsService $analyticsService,
        private CommissionService $commissionService,
        private DepositService $depositService
    ) {}

    /**
     * Sales-facing sold units tab.
     */
    public function soldUnits(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $perPage = ApiResponse::getPerPage($request, 15, 100);

        $units = $this->analyticsService->getSoldUnits($from, $to, $perPage);

        $data = collect($units->items())->map(function ($unit) {
            $contract = $unit->secondPartyData?->contract;
            $reservation = $unit->salesReservations->first();
            $commission = $unit->commission;

            return [
                'unit_id' => $unit->id,
                'project_name' => $contract?->project_name,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'final_selling_price' => (float) ($commission?->final_selling_price ?? 0),
                'commission_source' => $commission?->commission_source,
                'commission_percentage' => (float) ($commission?->commission_percentage ?? 0),
                'team_responsible' => $commission?->team_responsible ?? ($reservation?->marketingEmployee?->team),
                'marketing_employee_name' => $reservation?->marketingEmployee?->name,
                'status' => $unit->status,
                'confirmed_at' => $reservation?->confirmed_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $units->currentPage(),
                'last_page' => $units->lastPage(),
                'per_page' => $units->perPage(),
                'total' => $units->total(),
            ],
        ]);
    }

    /**
     * Commission summary for a sold unit.
     */
    public function soldUnitCommissionSummary(int $unitId): JsonResponse
    {
        $commission = Commission::where('contract_unit_id', $unitId)->first();

        if (!$commission) {
            return response()->json([
                'success' => false,
                'message' => 'Commission not found for this unit',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->commissionService->getCommissionSummary($commission),
        ]);
    }

    /**
     * Sales-facing deposit management tab (read-only wrapper).
     */
    public function depositsManagement(Request $request): JsonResponse
    {
        $deposits = $this->depositService->getDepositsForManagement(
            $request->query('status'),
            $request->query('from'),
            $request->query('to'),
            ApiResponse::getPerPage($request, 15, 100)
        );

        $data = collect($deposits->items())->map(function ($deposit) {
            return [
                'deposit_id' => $deposit->id,
                'project_name' => $deposit->contract?->project_name,
                'unit_number' => $deposit->contractUnit?->unit_number,
                'unit_type' => $deposit->contractUnit?->unit_type,
                'unit_price' => (float) ($deposit->contractUnit?->price ?? 0),
                'final_selling_price' => (float) ($deposit->salesReservation?->commission?->final_selling_price ?? 0),
                'deposit_amount' => (float) $deposit->amount,
                'payment_method' => $deposit->payment_method,
                'client_name' => $deposit->client_name,
                'payment_date' => optional($deposit->payment_date)->format('Y-m-d'),
                'commission_source' => $deposit->commission_source,
                'status' => $deposit->status,
                'can_refund' => $deposit->isRefundable(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'total' => $deposits->total(),
            ],
        ]);
    }

    /**
     * Sales-facing deposits follow-up tab.
     */
    public function depositsFollowUp(Request $request): JsonResponse
    {
        $deposits = $this->depositService->getDepositsForFollowUp(
            $request->query('from'),
            $request->query('to'),
            ApiResponse::getPerPage($request, 15, 100)
        );

        $data = collect($deposits->items())->map(function ($deposit) {
            $reservation = $deposit->salesReservation;

            return [
                'deposit_id' => $deposit->id,
                'project_name' => $deposit->contract?->project_name,
                'unit_number' => $deposit->contractUnit?->unit_number,
                'client_name' => $deposit->client_name,
                'final_selling_price' => (float) ($reservation?->commission?->final_selling_price ?? 0),
                'commission_percentage' => (float) ($reservation?->commission?->commission_percentage ?? 0),
                'commission_source' => $deposit->commission_source,
                'deposit_amount' => (float) $deposit->amount,
                'deposit_status' => $deposit->status,
                'is_refundable' => $deposit->isRefundable(),
                'claim_file_path' => $deposit->claim_file_path,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $deposits->currentPage(),
                'last_page' => $deposits->lastPage(),
                'per_page' => $deposits->perPage(),
                'total' => $deposits->total(),
            ],
        ]);
    }
}
