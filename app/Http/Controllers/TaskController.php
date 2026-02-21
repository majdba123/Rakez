<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Http\Responses\ApiResponse;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    /**
     * Create a new task (إضافة مهمة). Available to any authenticated user.
     * POST /api/tasks
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $task = $this->taskService->create($data, $request->user());

        return ApiResponse::created(
            new TaskResource($task->load(['team', 'creator', 'assignee'])),
            'تم حفظ المهمة بنجاح'
        );
    }

    /**
     * List tasks assigned to the current user (مهامي).
     * GET /api/my-tasks
     */
    public function myTasks(Request $request): JsonResponse
    {
        $tasks = $this->taskService->listForUser($request->user(), $request);

        return ApiResponse::success(
            TaskResource::collection($tasks->items())->resolve(),
            'تم جلب قائمة المهام بنجاح',
            200,
            [
                'pagination' => [
                    'total' => $tasks->total(),
                    'count' => $tasks->count(),
                    'per_page' => $tasks->perPage(),
                    'current_page' => $tasks->currentPage(),
                    'total_pages' => $tasks->lastPage(),
                    'has_more_pages' => $tasks->hasMorePages(),
                ],
            ]
        );
    }

    /**
     * Update task status (assignee only). Requires reason when status is could_not_complete.
     * PATCH /api/my-tasks/{id}/status
     */
    public function updateStatus(int $id, UpdateTaskStatusRequest $request): JsonResponse
    {
        $task = Task::find($id);

        if (! $task) {
            return ApiResponse::notFound('المهمة غير موجودة');
        }

        try {
            $task = $this->taskService->updateStatus(
                $task,
                $request->input('status'),
                $request->input('cannot_complete_reason'),
                $request->user()
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::forbidden($e->getMessage());
        }

        return ApiResponse::success(
            new TaskResource($task),
            'تم تحديث الحالة بنجاح'
        );
    }
}
