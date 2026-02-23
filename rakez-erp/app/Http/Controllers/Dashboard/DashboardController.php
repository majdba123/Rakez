<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * HR Dashboard Controller
 * لوحة تحكم الموارد البشرية
 */
class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * HR dashboard endpoint
     * GET /api/hr/dashboard
     */
    public function hr(): JsonResponse
    {
        try {
            $statistics = $this->dashboardService->getHrDashboardStatistics();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة تحكم الموارد البشرية بنجاح',
                'data' => $statistics,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}


