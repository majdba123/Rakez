<?php

namespace App\Services\Sales;

use App\Models\MarketingTask;
use App\Models\MarketingProject;
use App\Models\Contract;
use App\Models\SalesProjectAssignment;
use App\Models\User;
use App\Services\Marketing\MarketingNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MarketingTaskService
{
    public function __construct(
        private readonly MarketingNotificationService $notificationService
    ) {}
    /**
     * List marketing tasks visible to the leader (created by them or for projects they lead).
     */
    public function listTasksForLeader(User $leader, array $filters = []): LengthAwarePaginator
    {
        $contractIds = SalesProjectAssignment::where('leader_id', $leader->id)->pluck('contract_id');

        $query = MarketingTask::with(['contract', 'marketer'])
            ->where(function ($q) use ($leader, $contractIds) {
                $q->where('created_by', $leader->id)
                    ->orWhereIn('contract_id', $contractIds);
            });

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['contract_id'])) {
            $query->where('contract_id', $filters['contract_id']);
        }
        if (!empty($filters['marketer_id'])) {
            $query->where('marketer_id', $filters['marketer_id']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = min(max($perPage, 1), 100);

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get projects for task management (leader only).
     */
    public function getTaskProjects(User $leader): Collection
    {
        return Contract::whereHas('salesProjectAssignments', function ($q) use ($leader) {
            $q->where('leader_id', $leader->id);
        })->with(['montageDepartment', 'photographyDepartment', 'boardsDepartment'])->get();
    }

    /**
     * Get a single task by id (leader only; creator or has project access).
     */
    public function getTask(int $taskId, User $leader): MarketingTask
    {
        $task = MarketingTask::with(['contract', 'marketer'])->findOrFail($taskId);

        if ($task->created_by !== $leader->id && !$this->leaderHasAccessToProject($leader, $task->contract_id)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to view this task');
        }

        return $task;
    }

    /**
     * Delete a marketing task (leader only; creator or has project access).
     */
    public function deleteTask(int $taskId, User $leader): void
    {
        $task = MarketingTask::findOrFail($taskId);

        if ($task->created_by !== $leader->id && !$this->leaderHasAccessToProject($leader, $task->contract_id)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to delete this task');
        }

        $task->delete();
    }

    /**
     * Get project details for task.
     */
    public function getProjectForTask(int $contractId, User $leader): Contract
    {
        $contract = Contract::with(['montageDepartment', 'photographyDepartment', 'boardsDepartment'])->findOrFail($contractId);

        // Validate leader has access to this project
        if (!$this->leaderHasAccessToProject($leader, $contractId)) {
            throw new \Exception('Leader is not assigned to this project');
        }

        return $contract;
    }

    /**
     * Create a marketing task (leader only).
     */
    public function createTask(array $data, User $leader): MarketingTask
    {
        // Validate leader has access to this project
        if (!$this->leaderHasAccessToProject($leader, $data['contract_id'])) {
            throw new \Exception('Leader is not assigned to this project');
        }

        // Validate marketer is in same team
        $marketer = User::findOrFail($data['marketer_id']);
        if ($marketer->team !== $leader->team) {
            throw new \Exception('Marketer must be in the same team as leader');
        }

        $marketingProjectId = MarketingProject::where('contract_id', $data['contract_id'])->first()?->id;

        $task = MarketingTask::create([
            'contract_id' => $data['contract_id'],
            'marketing_project_id' => $marketingProjectId,
            'task_name' => $data['task_name'],
            'marketer_id' => $data['marketer_id'],
            'participating_marketers_count' => $data['participating_marketers_count'] ?? 4,
            'design_link' => $data['design_link'] ?? null,
            'design_number' => $data['design_number'] ?? null,
            'design_description' => $data['design_description'] ?? null,
            'status' => 'new',
            'created_by' => $leader->id,
        ]);

        $this->notificationService->notifyNewTask($task->marketer_id, $task->id);

        return $task;
    }

    /**
     * Update task status (leader only).
     */
    public function updateTaskStatus(int $taskId, string $status, User $leader): MarketingTask
    {
        $task = MarketingTask::findOrFail($taskId);

        // Validate leader created this task or has access to the project
        if ($task->created_by !== $leader->id && !$this->leaderHasAccessToProject($leader, $task->contract_id)) {
            throw new \Exception('Unauthorized to update this task');
        }

        $task->update(['status' => $status]);

        return $task->fresh();
    }

    /**
     * Check if leader has access to a project.
     */
    protected function leaderHasAccessToProject(User $leader, int $contractId): bool
    {
        return \App\Models\SalesProjectAssignment::where('leader_id', $leader->id)
            ->where('contract_id', $contractId)
            ->exists();
    }

    /**
     * Update task (used by controller).
     */
    public function updateTask(int $taskId, array $data, User $leader): MarketingTask
    {
        return $this->updateTaskStatus($taskId, $data['status'], $leader);
    }
}
