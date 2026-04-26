<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SalesExecutiveAvailableUnitsRequest;
use App\Http\Resources\Sales\SalesUnitSearchResource;
use App\Services\Sales\SalesExecutiveDashboardService;
use Illuminate\Http\JsonResponse;

class SalesExecutiveDashboardController extends Controller
{
    public function __construct(
        private SalesExecutiveDashboardService $executiveService
    ) {}

    /**
     * Available stock only: completed projects, status available, no active reservations; summary counts by `unit_type`.
     *
     * GET /api/sales/executive/available-units
     */
    public function availableUnits(SalesExecutiveAvailableUnitsRequest $request): JsonResponse
    {
        try {
            $filters = $request->validated();
            $summary = $this->executiveService->availableStockSummary($filters);
            $units = $this->executiveService->paginateAvailable($filters);

            return response()->json([
                'success' => true,
                'data' => SalesUnitSearchResource::collection($units->items()),
                'meta' => [
                    'current_page' => $units->currentPage(),
                    'last_page' => $units->lastPage(),
                    'per_page' => $units->perPage(),
                    'total' => $units->total(),
                ],
                'summary' => [
                    'total_available' => $summary['total'],
                    'by_type' => $summary['by_type'],
                    'by_type_list' => $summary['by_type_list'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر جلب بيانات الوحدات المتاحة: '.$e->getMessage(),
            ], 500);
        }
    }
}
