<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\ExpectedBooking;
use App\Models\MarketingBudgetDistribution;
use App\Models\User;
use App\Exports\DeveloperMarketingPlanExport;
use App\Exports\EmployeeMarketingPlanExport;
use App\Services\Marketing\DeveloperMarketingPlanService;
use App\Services\Marketing\MarketingDistributionBreakdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Response;
use App\Services\Pdf\PdfFactory;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\MpdfException;

class MarketingReportController extends Controller
{
    private const UNSUPPORTED_EXPORT_FORMAT_MESSAGE = 'صيغة التصدير غير مدعومة. استخدم pdf أو excel أو csv.';

    private const PDF_FONT_ERROR_MESSAGE = 'تعذّر توليد الـ PDF (خط غير متوفر). ضع ملف DejaVuSans.ttf في storage/fonts وراجع docs/PDF_ARABIC_FONTS.md';

    public function __construct(
        private DeveloperMarketingPlanService $developerPlanService,
        private MarketingDistributionBreakdownService $distributionBreakdownService
    ) {}
    public function projectPerformance(int $projectId): JsonResponse
    {
        $project = MarketingProject::with(['contract', 'expectedBooking', 'leads'])->findOrFail($projectId);

        return response()->json([
            'success' => true,
            'data' => [
                'project_name' => $project->contract->project_name,
                'total_leads' => $project->leads()->count(),
                'expected_bookings' => $project->expectedBooking->expected_bookings_count ?? 0,
                'conversion_rate' => $project->expectedBooking->conversion_rate ?? 0,
            ]
        ]);
    }

    public function budgetReport(): JsonResponse
    {
        $plans = EmployeeMarketingPlan::with('marketingProject.contract')->get();
        $totalMarketingValue = $plans->sum('marketing_value');

        $byProject = $plans->groupBy('marketing_project_id')->map(function ($projectPlans, $projectId) {
            $project = $projectPlans->first()?->marketingProject;
            return [
                'marketing_project_id' => (int) $projectId,
                'project_name' => $project?->contract?->project_name ?? null,
                'marketing_value' => round((float) $projectPlans->sum('marketing_value'), 2),
                'plans_count' => $projectPlans->count(),
            ];
        })->values()->toArray();

        $byPlanType = [
            'employee' => round((float) EmployeeMarketingPlan::sum('marketing_value'), 2),
            'developer' => round((float) MarketingBudgetDistribution::where('plan_type', 'developer')->sum('total_budget'), 2),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total_marketing_budget' => round((float) $totalMarketingValue, 2),
                'by_project' => $byProject,
                'by_plan_type' => $byPlanType,
            ]
        ]);
    }

    public function expectedBookingsReport(): JsonResponse
    {
        $totalExpected = ExpectedBooking::sum('expected_bookings_count');
        $totalExpectedValue = ExpectedBooking::sum('expected_booking_value');
        $totalCampaignBudget = MarketingBudgetDistribution::sum('total_budget');
        $depositValuePerBooking = $totalExpected > 0 ? ($totalCampaignBudget / $totalExpected) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_expected_bookings' => $totalExpected,
                'total_expected_booking_value' => round((float) $totalExpectedValue, 2),
                'total_campaign_budget' => round((float) $totalCampaignBudget, 2),
                'deposit_value_per_booking' => round((float) $depositValuePerBooking, 2),
            ]
        ]);
    }

    public function employeePerformance(int $userId): JsonResponse
    {
        $user = User::with(['employeeMarketingPlans', 'assignedLeads'])->findOrFail($userId);
        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'total_plans' => $user->employeeMarketingPlans()->count(),
                'total_leads_assigned' => $user->assignedLeads()->count(),
            ]
        ]);
    }

    /**
     * Export distribution as printable PDF (table: منصة، نقرات، مشاهدات + إجمالي + ملاحظات).
     * GET /api/marketing/reports/distribution/{planId}
     */
    public function exportDistribution(int $planId): StreamedResponse|Response
    {
        $plan = EmployeeMarketingPlan::with(['user', 'marketingProject.contract'])->findOrFail($planId);
        $platformDistribution = $plan->platform_distribution ?? [];
        $marketingValue = (float) $plan->marketing_value;

        if (empty($platformDistribution)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد توزيع منصات لهذه الخطة',
            ], 422);
        }

        $distribution = $this->distributionBreakdownService->buildPrintableDistributionTable(
            $marketingValue,
            $platformDistribution
        );

        return $this->downloadDistributionPdf($distribution, "توزيع_المنصات_خطة_{$planId}.pdf");
    }

    /**
     * Export project-level "الحملات الإعلانية على المنصات الإلكترونية" PDF (no employee selection).
     * GET /api/marketing/reports/distribution/project/{projectId}
     */
    public function exportDistributionByProject(int $projectId): StreamedResponse|Response|JsonResponse
    {
        $project = MarketingProject::with('contract')->findOrFail($projectId);
        $plans = EmployeeMarketingPlan::where('marketing_project_id', $projectId)->get();

        $platformAmountsSar = [];
        foreach ($plans as $plan) {
            $value = (float) $plan->marketing_value;
            $dist = $plan->platform_distribution ?? [];
            foreach ($dist as $platform => $percentage) {
                $pct = (float) $percentage;
                $platformAmountsSar[$platform] = ($platformAmountsSar[$platform] ?? 0) + $value * ($pct / 100);
            }
        }

        if (empty($platformAmountsSar)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد توزيع منصات لخطط هذا المشروع',
            ], 422);
        }

        $distribution = $this->distributionBreakdownService->buildPrintableDistributionFromAmounts($platformAmountsSar);
        $projectName = optional($project->contract)->project_name ?? 'مشروع';
        $safeName = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '_', (string) $projectName) ?: 'project';

        return $this->downloadDistributionPdf($distribution, "توزيع_المنصات_{$safeName}.pdf");
    }

    /**
     * Generate and download distribution PDF; on font/config failure return 503 JSON.
     */
    private function downloadDistributionPdf(array $distribution, string $filename): Response|JsonResponse
    {
        try {
            return PdfFactory::download('marketing.platform_distribution_print', [
                'distribution' => $distribution,
            ], $filename);
        } catch (MpdfException $e) {
            return response()->json([
                'success' => false,
                'message' => self::PDF_FONT_ERROR_MESSAGE,
                'detail'  => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }
    }

    public function exportPlan(int $planId, Request $request): StreamedResponse|BinaryFileResponse|JsonResponse|Response
    {
        $plan = EmployeeMarketingPlan::with(['user', 'marketingProject.contract'])->findOrFail($planId);
        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'pdf') {
            try {
                return PdfFactory::download('marketing.plan_export', ['plan' => $plan], "marketing_plan_{$planId}.pdf");
            } catch (MpdfException $e) {
                return response()->json([
                    'success' => false,
                    'message' => self::PDF_FONT_ERROR_MESSAGE,
                    'detail'  => config('app.debug') ? $e->getMessage() : null,
                ], 503);
            }
        }

        if ($format === 'excel') {
            return Excel::download(
                new EmployeeMarketingPlanExport($plan),
                "marketing_plan_{$planId}.xlsx"
            );
        }

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=marketing_plan_{$planId}.csv",
            ];

            return response()->streamDownload(function () use ($plan) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Plan ID', $plan->id]);
                fputcsv($output, ['Project', $plan->marketingProject->contract->project_name ?? '']);
                fputcsv($output, ['User', $plan->user->name ?? '']);
                fputcsv($output, ['Commission Value', $plan->commission_value]);
                fputcsv($output, ['Marketing Value', $plan->marketing_value]);
                fputcsv($output, []);
                fputcsv($output, ['Platform Distribution']);
                foreach (($plan->platform_distribution ?? []) as $platform => $percentage) {
                    fputcsv($output, [$platform, $percentage]);
                }
                fputcsv($output, []);
                fputcsv($output, ['Campaign Distribution']);
                foreach (($plan->campaign_distribution ?? []) as $campaign => $percentage) {
                    fputcsv($output, [$campaign, $percentage]);
                }
                fputcsv($output, []);
                fputcsv($output, ['Campaign Distribution By Platform']);
                foreach (($plan->campaign_distribution_by_platform ?? []) as $platform => $distribution) {
                    fputcsv($output, [$platform]);
                    foreach ((array) $distribution as $campaign => $percentage) {
                        fputcsv($output, ['', $campaign, $percentage]);
                    }
                }
                fclose($output);
            }, "marketing_plan_{$planId}.csv", $headers);
        }

        return response()->json([
            'success' => false,
            'message' => self::UNSUPPORTED_EXPORT_FORMAT_MESSAGE,
        ], 422);
    }

    public function exportDeveloperPlan(int $contractId, Request $request): StreamedResponse|BinaryFileResponse|JsonResponse|Response
    {
        $planData = $this->developerPlanService->getPlanForDeveloper($contractId);
        if (empty($planData['plan'])) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على خطة تسويق المطور لهذا العقد',
            ], 404);
        }

        $contract = Contract::find($contractId);
        $projectName = $contract?->project_name ?? null;

        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'pdf') {
            try {
                return PdfFactory::download('marketing.developer_plan_export', [
                    'contractId' => $contractId,
                    'projectName' => $projectName,
                    'plan' => $planData,
                ], "developer_marketing_plan_contract_{$contractId}.pdf");
            } catch (MpdfException $e) {
                return response()->json([
                    'success' => false,
                    'message' => self::PDF_FONT_ERROR_MESSAGE,
                    'detail'  => config('app.debug') ? $e->getMessage() : null,
                ], 503);
            }
        }

        if ($format === 'excel') {
            return Excel::download(
                new DeveloperMarketingPlanExport($contractId, $projectName, $planData),
                "developer_marketing_plan_contract_{$contractId}.xlsx"
            );
        }

        if ($format === 'csv') {
            $filename = "developer_marketing_plan_contract_{$contractId}.csv";
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ];

            return response()->streamDownload(function () use ($contractId, $projectName, $planData) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Developer Marketing Plan']);
                fputcsv($output, ['Contract ID', $contractId]);
                fputcsv($output, ['Project', $projectName ?? '']);
                fputcsv($output, []);
                fputcsv($output, ['Total Budget', $planData['total_budget_display'] ?? $planData['total_budget'] ?? '']);
                fputcsv($output, ['Expected Impressions', $planData['expected_impressions'] ?? '']);
                fputcsv($output, ['Expected Clicks', $planData['expected_clicks'] ?? '']);
                fputcsv($output, ['Marketing Duration', $planData['marketing_duration'] ?? '']);
                fclose($output);
            }, $filename, $headers);
        }

        return response()->json([
            'success' => false,
            'message' => self::UNSUPPORTED_EXPORT_FORMAT_MESSAGE,
        ], 422);
    }
}
