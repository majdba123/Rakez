<?php

namespace App\Services\Marketing;

use App\Constants\ReservationStatus;
use App\Models\MarketingProject;
use App\Models\MarketingProjectTeam;
use App\Models\SalesReservation;
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

    /**
     * Recommend an employee for client communication based on performance and developer-specific booking ratio.
     *
     * @return array{user: \App\Models\User|null, recommendation_reason: string, task_achievement_rate: float, booking_ratio: float}
     */
    public function recommendEmployeeForClient($projectId): array
    {
        $project = MarketingProject::with('contract')->findOrFail($projectId);
        $contractId = $project->contract_id;

        $totalProjectBookings = SalesReservation::where('contract_id', $contractId)
            ->whereIn('status', ReservationStatus::active())
            ->count();

        $taskService = app(MarketingTaskService::class);

        $scored = User::where('type', 'marketing')
            ->get()
            ->map(function ($user) use ($taskService, $contractId, $totalProjectBookings) {
                $achievementRate = $taskService->getTaskAchievementRate($user->id, now()->subDays(30)->toDateString());
                $userBookings = SalesReservation::where('contract_id', $contractId)
                    ->where('marketing_employee_id', $user->id)
                    ->whereIn('status', ReservationStatus::active())
                    ->count();
                $bookingRatio = $totalProjectBookings > 0 ? ($userBookings / $totalProjectBookings) : 0;
                $score = ($achievementRate / 100) * 0.6 + $bookingRatio * 0.4;

                return [
                    'user' => $user,
                    'task_achievement_rate' => round($achievementRate, 2),
                    'booking_ratio' => round($bookingRatio, 4),
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->first();

        if (!$scored) {
            return [
                'user' => null,
                'recommendation_reason' => 'No marketing employees available.',
                'task_achievement_rate' => 0.0,
                'booking_ratio' => 0.0,
            ];
        }

        $reason = $this->buildRecommendationReason(
            $scored['task_achievement_rate'],
            $scored['booking_ratio']
        );

        return [
            'user' => $scored['user'],
            'recommendation_reason' => $reason,
            'task_achievement_rate' => $scored['task_achievement_rate'],
            'booking_ratio' => $scored['booking_ratio'],
        ];
    }

    private function buildRecommendationReason(float $achievementRate, float $bookingRatio): string
    {
        $parts = [];

        if ($achievementRate >= 70) {
            $parts[] = 'High task achievement rate';
        } elseif ($achievementRate >= 40) {
            $parts[] = 'Moderate task achievement';
        } else {
            $parts[] = 'Available capacity';
        }

        if ($bookingRatio >= 0.2) {
            $parts[] = 'strong developer-specific booking history';
        } elseif ($bookingRatio > 0) {
            $parts[] = 'some experience with this developer';
        } else {
            $parts[] = 'balanced workload';
        }

        return implode(' and ', $parts) . '.';
    }
}
