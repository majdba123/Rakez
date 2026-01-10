<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
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

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات الوحدات بنجاح',
                'data' => $statistics,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $statistics = $this->dashboardService->getDashboardStatistics();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة التحكم بنجاح',
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

