<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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
        $user = $request->user();
        if (!$user) {
            return ApiResponse::unauthorized();
        }
        if (!$user->hasAnyRole(['sales', 'sales_leader', 'admin'])) {
            return ApiResponse::forbidden('Unauthorized. Sales role required.');
        }

        try {
            $scope = $request->query('scope', 'me');
            $from = $request->query('from');
            $to = $request->query('to');

            $kpis = $this->dashboardService->getKPIs($scope, $from, $to, $user);

            return ApiResponse::success($kpis);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }
}
