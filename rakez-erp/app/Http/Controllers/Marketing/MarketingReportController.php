<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\ExpectedBooking;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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

    public function exportPlan(int $planId, string $format): JsonResponse
    {
        // Placeholder for PDF/Excel export
        return response()->json([
            'success' => true,
            'message' => "Exporting plan #{$planId} as {$format} (feature coming soon)"
        ]);
    }
}
