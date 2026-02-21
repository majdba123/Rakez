<?php

namespace App\Services\HR;

use App\Models\HrTask;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HrTaskService
{
    /**
     * Create a new HR task.
     */
    public function create(array $data, User $creator): HrTask
    {
        return HrTask::create([
            'task_name' => $data['task_name'],
            'team_id' => $data['team_id'],
            'due_at' => $data['due_at'],
            'assigned_to' => $data['assigned_to'],
            'status' => $data['status'] ?? HrTask::STATUS_IN_PROGRESS,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * List tasks assigned to the given user (مهامي), with pagination and optional status filter.
     */
    public function listForUser(User $user, Request $request): LengthAwarePaginator
    {
        $query = HrTask::query()
            ->assignedTo($user->id)
            ->with(['team', 'creator']);

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        $query->orderByDesc('created_at');

        $perPage = \App\Http\Responses\ApiResponse::getPerPage($request, 15, 100);

        return $query->paginate($perPage);
    }

    /**
     * Update task status (assignee only). When status is could_not_complete, reason is required.
     *
     * @throws InvalidArgumentException when user is not the assignee or reason missing for could_not_complete
     */
    public function updateStatus(HrTask $task, string $status, ?string $reason, User $user): HrTask
    {
        if ((int) $task->assigned_to !== (int) $user->id) {
            throw new InvalidArgumentException(__('You can only update status of tasks assigned to you.'));
        }

        if ($status === HrTask::STATUS_COULD_NOT_COMPLETE && empty(trim((string) $reason))) {
            throw new InvalidArgumentException(__('Reason is required when status is "could not complete".'));
        }

        $task->status = $status;
        if ($status === HrTask::STATUS_COULD_NOT_COMPLETE) {
            $task->cannot_complete_reason = $reason;
        } else {
            $task->cannot_complete_reason = null;
        }
        $task->save();

        return $task->fresh(['team', 'creator', 'assignee']);
    }
}
