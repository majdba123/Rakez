<?php

namespace App\Services\HR;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

/**
 * Service for manager task API. Lists and shows tasks of manager's employees (same team).
 */
class ManagerTaskService
{
    /**
     * Get task query scoped to manager's team employees.
     * Tasks where assigned_to is in manager's team (users with team_id = manager.team_id).
     */
    private function tasksForManager(User $manager)
    {
        if (!$manager->team_id) {
            return Task::query()->whereRaw('1 = 0'); // No team = no tasks
        }

        $teamMemberIds = User::where('team_id', $manager->team_id)->pluck('id');

        return Task::with(['team:id,name', 'assignee:id,name,email', 'creator:id,name'])
            ->whereIn('assigned_to', $teamMemberIds);
    }

    /**
     * List tasks of manager's employees.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listTasks(User $manager, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->tasksForManager($manager);

        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== null && $filters['assigned_to'] !== '') {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['section']) && $filters['section'] !== null && $filters['section'] !== '') {
            $query->where('section', $filters['section']);
        }

        $sortBy = $filters['sort_by'] ?? 'due_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc');
        $allowedSort = ['id', 'task_name', 'due_at', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSort)) {
            $sortBy = 'due_at';
        }
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'asc';
        }
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Show a single task. Manager can only view tasks of their team's employees.
     */
    public function showTask(User $manager, int $taskId): Task
    {
        $task = $this->tasksForManager($manager)->find($taskId);

        if (!$task) {
            throw new Exception('المهمة غير موجودة أو لا يمكنك الوصول إليها.');
        }

        return $task->load(['team:id,name', 'assignee:id,name,email,phone', 'creator:id,name']);
    }

    /**
     * Get task statistics for manager's employees.
     */
    public function getStatistics(User $manager): array
    {
        $query = $this->tasksForManager($manager);

        $total = (clone $query)->count();
        $inProgress = (clone $query)->where('status', Task::STATUS_IN_PROGRESS)->count();
        $completed = (clone $query)->where('status', Task::STATUS_COMPLETED)->count();
        $couldNotComplete = (clone $query)->where('status', Task::STATUS_COULD_NOT_COMPLETE)->count();

        return [
            'total' => $total,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'could_not_complete' => $couldNotComplete,
        ];
    }
}
