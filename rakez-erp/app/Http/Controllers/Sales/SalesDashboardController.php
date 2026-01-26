<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\SalesDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesDashboardController extends Controller
{
    public function __construct(
        private SalesDashboardService $dashboardService
    ) {}

    /**
     * Get sales dashboard KPIs.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $scope = $request->query('scope', 'me');
            $from = $request->query('from');
            $to = $request->query('to');
            $user = $request->user();

            $kpis = $this->dashboardService->getKPIs($scope, $from, $to, $user);

            return response()->json([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
