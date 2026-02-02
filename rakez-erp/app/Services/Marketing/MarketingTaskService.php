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

    public function getDailyTasks($userId, $date = null, $status = null)
    {
        $query = MarketingTask::where('marketer_id', $userId);
        
        if ($date) {
            $query->whereDate('created_at', $date);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->get();
    }

    public function updateTaskStatus($taskId, $status)
    {
        $task = MarketingTask::findOrFail($taskId);
        $task->update(['status' => $status]);
        return $task;
    }

    public function getTaskAchievementRate($userId, $date = null)
    {
        $query = MarketingTask::where('marketer_id', $userId);
        
        if ($date) {
            $query->whereDate('created_at', $date);
        }
        
        $tasks = $query->get();

        if ($tasks->isEmpty()) return 0;

        $completed = $tasks->where('status', 'completed')->count();
        return ($completed / $tasks->count()) * 100;
    }
}
