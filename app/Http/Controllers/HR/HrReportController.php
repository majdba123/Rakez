<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\HrReportService;
use Barryvdh\DomPDF\Facade\Pdf;
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
    public function marketerPerformance(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;

            $report = $this->reportService->getMarketerPerformanceReport($year, $month, $teamId);

            if (strtolower((string) $request->input('format')) === 'pdf') {
                return $this->marketerPerformancePdf($request);
            }

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
     * Download marketer performance report as PDF.
     * GET /hr/reports/marketer-performance/pdf
     */
    public function marketerPerformancePdf(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;

        $report = $this->reportService->getMarketerPerformanceReport($year, $month, $teamId);
        $generatedAt = now()->toIso8601String();

        $html = view('pdfs.marketer_performance_report', [
            'report' => $report,
            'generated_at' => $generatedAt,
        ])->render();

        $filename = sprintf('marketer_performance_%d_%02d.pdf', $year, $month);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->download($filename);
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
     * Get expiring contracts and probation-ending report (عقود قريبة من الانتهاء وقرب انتهاء فترة التجربة).
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
                'message' => 'تم إنشاء تقرير العقود القريبة من الانتهاء وقرب انتهاء فترة التجربة بنجاح',
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
     * Download expiring contracts report as PDF.
     * GET /hr/reports/expiring-contracts/pdf
     */
    public function expiringContractsPdf(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $days = max(1, min(365, $days));

        $report = $this->reportService->getExpiringContractsReport($days);

        $html = view('pdfs.expiring_contracts_report', [
            'report' => $report,
            'days' => $days,
        ])->render();

        $filename = sprintf('expiring_contracts_%ddays.pdf', $days);

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * Get ended/expired contracts report (عقود منتهية).
     * GET /hr/reports/ended-contracts
     */
    public function endedContracts(Request $request): JsonResponse
    {
        try {
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            $status = $request->input('status'); // expired | terminated

            $report = $this->reportService->getEndedContractsReport($fromDate, $toDate, $status);

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

