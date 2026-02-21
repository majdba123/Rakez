<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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

            return ApiResponse::success($statistics, 'تم جلب إحصائيات لوحة تحكم الموارد البشرية بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}


