<?php

namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingProjectTeam;
use App\Models\User;
use App\Models\SalesReservation;

class TeamManagementService
{
    public function assignTeamToProject($projectId, $userIds)
    {
        MarketingProjectTeam::where('marketing_project_id', $projectId)->delete();

        $assignments = [];
        foreach ($userIds as $userId) {
            $assignments[] = MarketingProjectTeam::create([
                'marketing_project_id' => $projectId,
                'user_id' => $userId,
                'role' => 'marketer'
            ]);
        }

        return $assignments;
    }

    public function getProjectTeam($projectId)
    {
        return MarketingProjectTeam::with('user')
            ->where('marketing_project_id', $projectId)
            ->get();
    }

    public function recommendEmployeeForClient($projectId)
    {
        $project = MarketingProject::with('contract')->findOrFail($projectId);
        $contractId = $project->contract_id;

        $totalProjectBookings = SalesReservation::where('contract_id', $contractId)
            ->whereIn('status', ['under_negotiation', 'confirmed'])
            ->count();

        $taskService = app(MarketingTaskService::class);

        return User::where('type', 'marketing')
            ->get()
            ->sortByDesc(function ($user) use ($taskService, $contractId, $totalProjectBookings) {
                $achievementRate = $taskService->getTaskAchievementRate($user->id, now()->subDays(30)->toDateString());

                $userBookings = SalesReservation::where('contract_id', $contractId)
                    ->where('marketing_employee_id', $user->id)
                    ->whereIn('status', ['under_negotiation', 'confirmed'])
                    ->count();

                $bookingRatio = $totalProjectBookings > 0 ? ($userBookings / $totalProjectBookings) : 0;

                return ($achievementRate / 100) * 0.6 + $bookingRatio * 0.4;
            })
            ->first();
    }
}
