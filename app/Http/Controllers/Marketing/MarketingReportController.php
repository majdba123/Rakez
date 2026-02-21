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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class MarketingReportController extends Controller
{
    private const UNSUPPORTED_EXPORT_FORMAT_MESSAGE = 'Unsupported export format. Use pdf, excel, or csv.';

    public function __construct(
        private DeveloperMarketingPlanService $developerPlanService
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

    public function exportPlan(int $planId, Request $request): StreamedResponse|BinaryFileResponse|JsonResponse|Response
    {
        $plan = EmployeeMarketingPlan::with(['user', 'marketingProject.contract'])->findOrFail($planId);
        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'pdf') {
            $html = view('marketing.plan_export', ['plan' => $plan])->render();
            return Pdf::loadHTML($html)->download("marketing_plan_{$planId}.pdf");
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
        if (!$planData) {
            return response()->json([
                'success' => false,
                'message' => 'Developer marketing plan not found for this contract',
            ], 404);
        }

        $contract = Contract::find($contractId);
        $projectName = $contract?->project_name ?? null;

        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'pdf') {
            $html = view('marketing.developer_plan_export', [
                'contractId' => $contractId,
                'projectName' => $projectName,
                'plan' => $planData,
            ])->render();
            return Pdf::loadHTML($html)->download("developer_marketing_plan_contract_{$contractId}.pdf");
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
                fputcsv($output, ['Total Budget', $planData['total_budget'] ?? '']);
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
