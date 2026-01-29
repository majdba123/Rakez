<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Services\Credit\CreditDashboardService;
use Illuminate\Http\JsonResponse;
use Exception;

class CreditDashboardController extends Controller
{
    protected CreditDashboardService $dashboardService;

    public function __construct(CreditDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get Credit dashboard KPIs.
     * GET /credit/dashboard
     */
    public function index(): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getKpis();
            $stageBreakdown = $this->dashboardService->getStageBreakdown();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة تحكم الائتمان بنجاح',
                'data' => [
                    'kpis' => $kpis,
                    'stage_breakdown' => $stageBreakdown,
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
     * Refresh dashboard cache.
     * POST /credit/dashboard/refresh
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

