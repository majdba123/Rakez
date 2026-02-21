<?php

namespace App\Services\Marketing;

use App\Models\MarketingTask;
use App\Models\MarketingProject;
use App\Models\User;
use App\Services\Marketing\MarketingNotificationService;

class MarketingTaskService
{
    public function __construct(
        private readonly MarketingNotificationService $notificationService
    ) {}

    public function createTask($data)
    {
        if (isset($data['contract_id']) && !isset($data['marketing_project_id'])) {
            $data['marketing_project_id'] = MarketingProject::where('contract_id', $data['contract_id'])->first()?->id;
        }

        $task = MarketingTask::create($data);
        $this->notificationService->notifyNewTask($task->marketer_id, $task->id);

        return $task;
    }

    /**
     * Get daily tasks for a marketer, optionally filtered by date (creation or due date).
     *
     * @param int $userId
     * @param string|null $date Optional date (Y-m-d) to filter tasks
     * @param string|null $status Optional status filter
     * @param int $perPage
     * @param bool $filterByDueDate When true and $date is set, filter by due_date; otherwise filter by created_at
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDailyTasks($userId, $date = null, $status = null, int $perPage = 15, bool $filterByDueDate = false)
    {
        $query = MarketingTask::where('marketer_id', $userId);

        if ($date) {
            if ($filterByDueDate) {
                $query->whereDate('due_date', $date);
            } else {
                $query->whereDate('created_at', $date);
            }
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
