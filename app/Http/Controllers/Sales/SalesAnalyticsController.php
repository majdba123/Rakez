<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\SalesAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SalesAnalyticsController extends Controller
{
    protected SalesAnalyticsService $analyticsService;

    public function __construct(SalesAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get dashboard KPIs.
     *
     * @group Sales Analytics
     */
    public function dashboard(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $kpis = $this->analyticsService->getDashboardKPIs(
            $request->input('from'),
            $request->input('to')
        );

        return ApiResponse::success($kpis, 'تم جلب لوحة التحكم بنجاح');
    }

    /**
     * Get sold units list.
     *
     * @group Sales Analytics
     */
    public function soldUnits(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $soldUnits = $this->analyticsService->getSoldUnits(
            $request->input('from'),
            $request->input('to'),
            ApiResponse::getPerPage($request, 15, 100)
        );

        return ApiResponse::success($soldUnits, 'تم جلب الوحدات المباعة بنجاح');
    }

    /**
     * Get deposit statistics by project.
     *
     * @group Sales Analytics
     */
    public function depositStatsByProject(Request $request, int $contractId): JsonResponse
    {
        $stats = $this->analyticsService->getDepositStatsByProject($contractId);

        return ApiResponse::success($stats, 'تم جلب إحصائيات الودائع بنجاح');
    }

    /**
     * Get commission statistics by employee.
     *
     * @group Sales Analytics
     */
    public function commissionStatsByEmployee(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $stats = $this->analyticsService->getCommissionStatsByEmployee(
            $userId,
            $request->input('from'),
            $request->input('to')
        );

        return ApiResponse::success($stats, 'تم جلب إحصائيات العمولة بنجاح');
    }

    /**
     * Get monthly commission report for all employees.
     *
     * @group Sales Analytics
     */
    public function monthlyCommissionReport(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $report = $this->analyticsService->getMonthlyCommissionReport(
            $request->input('year'),
            $request->input('month')
        );

        return ApiResponse::success($report, 'تم جلب التقرير الشهري بنجاح');
    }
}
