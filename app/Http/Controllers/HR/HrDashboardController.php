<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\HR\HrDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class HrDashboardController extends Controller
{
    protected HrDashboardService $dashboardService;

    public function __construct(HrDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get HR dashboard KPIs.
     * GET /hr/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return ApiResponse::unauthorized();
        }
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $kpis = $this->dashboardService->getDashboardKpis($year, $month);
            $byDepartment = $this->dashboardService->getEmployeesByDepartment();

            return ApiResponse::success([
                'kpis' => $kpis,
                'employees_by_department' => $byDepartment,
            ], 'تم جلب إحصائيات لوحة تحكم الموارد البشرية بنجاح', 200, [
                'period' => [
                    'year' => $year,
                    'month' => $month,
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Clear dashboard cache.
     * POST /hr/dashboard/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $this->dashboardService->clearCache();

            return ApiResponse::success(null, 'تم تحديث البيانات بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}

