<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MyTasksController extends Controller
{
    /**
     * Create a new task.
     * POST /api/tasks
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        $data = $request->validated();

        // Ensure section and team_id are consistent with the assignee
        $assignee = User::findOrFail($data['assigned_to']);
        $data['section'] = $data['section'] ?? $assignee->type;
        if (empty($data['team_id']) && $assignee->team_id) {
            $data['team_id'] = $assignee->team_id;
        }

        // Default status to in_progress; creator does not need to choose it
        $data['status'] = Task::STATUS_IN_PROGRESS;
        $data['created_by'] = $user->id;
        $task = Task::create($data);
        $task->load(['team:id,name', 'assignee:id,name', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'message' => __('Task created.'),
            'data' => $task,
        ], 201);
    }
    /**
     * List tasks assigned to the authenticated user.
     * GET /api/my-tasks
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $query = Task::query()
            ->where('assigned_to', $user->id)
            ->with(['team:id,name', 'creator:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderBy('due_at');

        $tasks = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tasks->items(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ], 200);
    }

    /**
     * Update status of a task assigned to the current user.
     * PATCH /api/my-tasks/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        $task = Task::where('assigned_to', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                Task::STATUS_IN_PROGRESS,
                Task::STATUS_COMPLETED,
                Task::STATUS_COULD_NOT_COMPLETE,
            ])],
            'cannot_complete_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $task->status = $validated['status'];
        if (isset($validated['cannot_complete_reason'])) {
            $task->cannot_complete_reason = $validated['cannot_complete_reason'];
        }
        $task->save();

        return response()->json([
            'success' => true,
            'message' => __('Task status updated.'),
            'data' => $task->fresh(['team:id,name', 'creator:id,name']),
        ], 200);
    }
}
