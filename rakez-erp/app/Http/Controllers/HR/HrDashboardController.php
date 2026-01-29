<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
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
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $kpis = $this->dashboardService->getDashboardKpis($year, $month);
            $byDepartment = $this->dashboardService->getEmployeesByDepartment();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة تحكم الموارد البشرية بنجاح',
                'data' => [
                    'kpis' => $kpis,
                    'employees_by_department' => $byDepartment,
                ],
                'meta' => [
                    'period' => [
                        'year' => $year,
                        'month' => $month,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

