<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AccountingDashboardController extends Controller
{
    protected AccountingDashboardService $dashboardService;

    public function __construct(AccountingDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get accounting dashboard metrics.
     * GET /api/accounting/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
            ]);

            $metrics = $this->dashboardService->getDashboardMetrics(
                $request->input('from_date'),
                $request->input('to_date')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات لوحة المحاسبة بنجاح',
                'data' => $metrics,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
