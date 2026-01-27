<?php

namespace App\Services\Marketing;

use App\Models\MarketingProject;
use App\Models\MarketingProjectTeam;
use App\Models\User;

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
        // Simple recommendation logic based on task achievement rate
        return User::where('type', 'marketing')
            ->get()
            ->sortByDesc(function ($user) {
                $service = new MarketingTaskService();
                return $service->getTaskAchievementRate($user->id, now()->subDays(30)->toDateString());
            })
            ->first();
    }
}
