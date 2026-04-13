<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Services\Workflow\WorkflowTaskAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTasksController extends Controller
{
    public function __construct(
        protected WorkflowTaskAdminService $taskAdminService
    ) {}

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

        $task = $this->taskAdminService->create($data, $user);

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
            ->with(['team:id,name', 'creator:id,name', 'assignee:id,name']);

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
     * List tasks created by the authenticated user and assigned to others.
     * GET /api/requested-tasks
     */
    public function requestedTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $perPage = min((int) $request->input('per_page', 10), 100);
        $query = Task::query()
            ->where('created_by', $user->id)
            ->with(['team:id,name', 'assignee:id,name']);

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

        $task = $this->taskAdminService->updateStatus($task, $request->all());

        return response()->json([
            'success' => true,
            'message' => __('Task status updated.'),
            'data' => $task,
        ], 200);
    }
}
