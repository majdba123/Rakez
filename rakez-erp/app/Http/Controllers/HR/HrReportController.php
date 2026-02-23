<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\HrReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class HrReportController extends Controller
{
    protected HrReportService $reportService;

    public function __construct(HrReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get monthly team performance report.
     * GET /hr/reports/team-performance
     */
    public function teamPerformance(Request $request): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $report = $this->reportService->getTeamPerformanceReport($year, $month);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء تقرير أداء الفرق بنجاح',
                'data' => $report,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get marketer performance report.
     * GET /hr/reports/marketer-performance
     */
    public function marketerPerformance(Request $request): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;

            $report = $this->reportService->getMarketerPerformanceReport($year, $month, $teamId);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء تقرير أداء المسوقين بنجاح',
                'data' => $report,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee count report.
     * GET /hr/reports/employee-count
     */
    public function employeeCount(): JsonResponse
    {
        try {
            $report = $this->reportService->getEmployeeCountReport();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء تقرير عدد الموظفين بنجاح',
                'data' => $report,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get expiring contracts report.
     * GET /hr/reports/expiring-contracts
     */
    public function expiringContracts(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->input('days', 30);
            $days = max(1, min(365, $days)); // Limit between 1-365 days

            $report = $this->reportService->getExpiringContractsReport($days);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء تقرير العقود المنتهية بنجاح',
                'data' => $report,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

