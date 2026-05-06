<?php

namespace App\Http\Controllers\Accounting;

use App\Exceptions\Accounting\UnitPriceUnresolvedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\GenerateUnitCommissionRequest;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\SalesReservation;
use App\Services\Accounting\UnitCommissionGenerationService;
use App\Services\Accounting\UnitCommissionPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UnitCommissionGenerationController extends Controller
{
    public function __construct(
        private UnitCommissionGenerationService $unitCommissionGenerationService,
        private UnitCommissionPreviewService $unitCommissionPreviewService,
    ) {}

    /**
     * POST /api/accounting/reservations/{reservation}/generate-unit-commission
     */
    public function generate(GenerateUnitCommissionRequest $request, SalesReservation $reservation): JsonResponse
    {
        try {
            $commission = $this->unitCommissionGenerationService->generate(
                $reservation,
                $request->user(),
            );

            $meta = $this->unitCommissionPreviewService->preview($reservation->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Unit commission generated successfully.',
                'data' => [
                    'commission' => $this->commissionPayload($commission),
                    'distributions' => $this->distributionsPayload($commission),
                    'unresolved' => $meta['unresolved'] ?? [],
                    'total_distributed' => $meta['total_distributed'] ?? 0,
                    'remaining_amount' => $meta['remaining_amount'] ?? 0,
                ],
            ]);
        } catch (UnitPriceUnresolvedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['unit_price' => [$e->getMessage()]],
            ], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /** @return array<string, mixed> */
    protected function commissionPayload(Commission $commission): array
    {
        return [
            'id' => $commission->id,
            'reservation_id' => (int) $commission->sales_reservation_id,
            'contract_unit_id' => (int) $commission->contract_unit_id,
            'final_selling_price' => (float) $commission->final_selling_price,
            'commission_source' => (string) $commission->commission_source,
            'commission_percentage' => (float) $commission->commission_percentage,
            'total_amount' => (float) $commission->total_amount,
            'net_amount' => (float) $commission->net_amount,
            'calculation_formula_key' => $commission->calculation_formula_key,
            'calculated_by_project_setting' => (bool) $commission->calculated_by_project_setting,
            'project_commission_setting_id' => $commission->project_commission_setting_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function distributionsPayload(Commission $commission): array
    {
        return $commission->distributions
            ->map(fn (CommissionDistribution $d) => [
                'user_id' => $d->user_id !== null ? (int) $d->user_id : null,
                'percentage' => (float) $d->percentage,
                'amount' => (float) $d->amount,
                'source_scope' => $d->source_scope,
                'source_type' => $d->source_type,
            ])
            ->values()
            ->all();
    }
}
