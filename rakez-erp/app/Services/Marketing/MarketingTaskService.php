<?php

namespace App\Services\Marketing;

use App\Models\MarketingTask;
use App\Models\User;

class MarketingTaskService
{
    public function createTask($data)
    {
        return MarketingTask::create($data);
    }

    public function getDailyTasks($userId, $date = null)
    {
        $date = $date ?: now()->toDateString();
        return MarketingTask::where('marketer_id', $userId)
            ->whereDate('due_date', $date)
            ->get();
    }

    public function updateTaskStatus($taskId, $status)
    {
        $task = MarketingTask::findOrFail($taskId);
        $task->update(['status' => $status]);
        return $task;
    }

    public function getTaskAchievementRate($userId, $date = null)
    {
        $date = $date ?: now()->toDateString();
        $tasks = MarketingTask::where('marketer_id', $userId)
            ->whereDate('due_date', $date)
            ->get();

        if ($tasks->isEmpty()) return 0;

        $completed = $tasks->where('status', 'completed')->count();
        return ($completed / $tasks->count()) * 100;
    }
}
