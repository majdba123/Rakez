<?php

namespace App\Services\Marketing;

use App\Models\MarketingTask;
use App\Models\User;
use App\Services\Marketing\MarketingNotificationService;

class MarketingTaskService
{
    public function __construct(
        private readonly MarketingNotificationService $notificationService
    ) {}

    public function createTask($data)
    {
        $task = MarketingTask::create($data);
        $this->notificationService->notifyNewTask($task->marketer_id, $task->id);

        return $task;
    }

    /**
     * @param int $userId
     * @param string|null $date
     * @param string|null $status
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDailyTasks($userId, $date = null, $status = null, int $perPage = 15)
    {
        $query = MarketingTask::where('marketer_id', $userId);
        
        if ($date) {
            $query->whereDate('created_at', $date);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
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
