<?php

namespace App\Services\Workflow;

use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WorkflowTaskAdminService
{
    public function create(array $data, User $actor): Task
    {
        $assignee = User::findOrFail($data['assigned_to']);

        $payload = [
            'task_name' => $data['task_name'],
            'section' => $data['section'] ?? $assignee->type,
            'team_id' => $data['team_id'] ?? $assignee->team_id,
            'due_at' => $data['due_at'] ?? now()->addDay(),
            'assigned_to' => $assignee->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'created_by' => $actor->id,
        ];

        return Task::create($payload)->fresh(['team:id,name', 'creator:id,name', 'assignee:id,name']);
    }

    public function updateStatus(Task $task, array $data): Task
    {
        $validator = validator($data, [
            'status' => ['required', 'string', Rule::in([
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_COMPLETED,
                Task::STATUS_COULD_NOT_COMPLETE,
            ])],
            'cannot_complete_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $task->status = $validated['status'];
        $task->cannot_complete_reason = $validated['status'] === Task::STATUS_COULD_NOT_COMPLETE
            ? ($validated['cannot_complete_reason'] ?? null)
            : null;
        $task->save();

        return $task->fresh(['team:id,name', 'creator:id,name', 'assignee:id,name']);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getAssignedTasksForAiSkill(User $user, int $perPage = 10, ?string $status = null): array
    {
        $paginator = $this->buildAssignedTasksQuery($user, $status)
            ->paginate(min(max($perPage, 1), 25));

        return $this->mapTaskPaginator($paginator);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getRequestedTasksForAiSkill(User $user, int $perPage = 10, ?string $status = null): array
    {
        $query = Task::query()
            ->where('created_by', $user->id)
            ->with(['team:id,name', 'assignee:id,name'])
            ->orderBy('due_at');

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate(min(max($perPage, 1), 25));

        return $this->mapTaskPaginator($paginator);
    }

    private function buildAssignedTasksQuery(User $user, ?string $status = null)
    {
        $query = Task::query()
            ->where('assigned_to', $user->id)
            ->with(['team:id,name', 'creator:id,name', 'assignee:id,name'])
            ->orderBy('due_at');

        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function mapTaskPaginator(LengthAwarePaginator $paginator): array
    {
        $items = collect($paginator->items())->map(fn (Task $task) => [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'section' => $task->section,
            'status' => $task->status,
            'due_at' => $task->due_at?->toIso8601String(),
            'cannot_complete_reason' => $task->cannot_complete_reason,
            'team_name' => $task->team?->name,
            'creator_name' => $task->creator?->name,
            'assignee_name' => $task->assignee?->name,
        ])->values()->all();

        return [
            'items' => $items,
            'total' => $paginator->total(),
        ];
    }
}
