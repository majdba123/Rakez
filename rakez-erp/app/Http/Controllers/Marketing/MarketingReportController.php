<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\ExpectedBooking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;

class MarketingReportController extends Controller
{
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
        $totalMarketingValue = EmployeeMarketingPlan::sum('marketing_value');
        return response()->json([
            'success' => true,
            'data' => [
                'total_marketing_budget' => $totalMarketingValue,
            ]
        ]);
    }

    public function expectedBookingsReport(): JsonResponse
    {
        $totalExpected = ExpectedBooking::sum('expected_bookings_count');
        return response()->json([
            'success' => true,
            'data' => [
                'total_expected_bookings' => $totalExpected,
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

    public function exportPlan(int $planId, Request $request): StreamedResponse|JsonResponse|Response
    {
        $plan = EmployeeMarketingPlan::with(['user', 'marketingProject.contract'])->findOrFail($planId);
        $format = strtolower($request->query('format', 'pdf'));

        if ($format === 'pdf') {
            $html = view('marketing.plan_export', ['plan' => $plan])->render();
            return Pdf::loadHTML($html)->download("marketing_plan_{$planId}.pdf");
        }

        if ($format === 'excel') {
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
                fclose($output);
            }, "marketing_plan_{$planId}.csv", $headers);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unsupported export format'
        ], 422);
    }
}
