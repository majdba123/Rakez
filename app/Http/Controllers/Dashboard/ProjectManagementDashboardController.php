<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Dashboard\ProjectManagementDashboardService;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * لوحة تحكم إدارة المشاريع
 * Project Management Dashboard Controller
 */
class ProjectManagementDashboardController extends Controller
{
    protected ProjectManagementDashboardService $dashboardService;

    public function __construct(ProjectManagementDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function unitsStatistics(): JsonResponse
    {
        try {
            $statistics = $this->dashboardService->getUnitsStatistics();

            return ApiResponse::success($statistics, 'تم جلب إحصائيات الوحدات بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $statistics = $this->dashboardService->getDashboardStatistics();

            return ApiResponse::success($statistics, 'تم جلب إحصائيات لوحة التحكم بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}

