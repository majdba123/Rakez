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
        // Check if user has required sales role
        $user = $request->user();
        if (!$user->hasAnyRole(['sales', 'sales_leader', 'admin'])) {
            abort(403, 'Unauthorized. Sales role required.');
        }

        try {
            $scope = $request->query('scope', 'me');
            $from = $request->query('from');
            $to = $request->query('to');

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
