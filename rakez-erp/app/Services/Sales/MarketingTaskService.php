<?php

namespace App\Services\Sales;

use App\Models\MarketingTask;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Collection;

class MarketingTaskService
{
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

        return MarketingTask::create([
            'contract_id' => $data['contract_id'],
            'task_name' => $data['task_name'],
            'marketer_id' => $data['marketer_id'],
            'participating_marketers_count' => $data['participating_marketers_count'] ?? 4,
            'design_link' => $data['design_link'] ?? null,
            'design_number' => $data['design_number'] ?? null,
            'design_description' => $data['design_description'] ?? null,
            'created_by' => $leader->id,
        ]);
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
     * Alias for getTaskProjects (used by controller).
     */
    public function listTaskProjects(User $leader): Collection
    {
        return $this->getTaskProjects($leader);
    }

    /**
     * Update task (used by controller).
     */
    public function updateTask(int $taskId, array $data, User $leader): MarketingTask
    {
        return $this->updateTaskStatus($taskId, $data['status'], $leader);
    }
}
