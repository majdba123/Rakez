<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreMarketingTaskRequest;
use App\Http\Requests\Marketing\UpdateMarketingTaskRequest;
use App\Services\Marketing\MarketingTaskService;
use App\Models\MarketingTask;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingTaskController extends Controller
{
    public function __construct(
        private MarketingTaskService $taskService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\MarketingTask::class);

        $perPage = ApiResponse::getPerPage($request);
        $filterByDueDate = filter_var($request->query('filter_by_due_date', false), FILTER_VALIDATE_BOOLEAN);
        $tasks = $this->taskService->getDailyTasks(
            $request->user()->id,
            $request->query('date'),
            $request->query('status'),
            $perPage,
            $filterByDueDate
        );

        return ApiResponse::paginated($tasks, 'تم جلب قائمة المهام بنجاح');
    }

    public function store(StoreMarketingTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        
        $task = $this->taskService->createTask($data);

        return response()->json([
            'success' => true,
            'message' => 'Marketing task created successfully',
            'data' => $task
        ], 201);
    }

    public function update(int $taskId, UpdateMarketingTaskRequest $request): JsonResponse
    {
        $task = MarketingTask::findOrFail($taskId);
        $task->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Marketing task updated successfully',
            'data' => $task
        ]);
    }

    public function updateStatus(int $taskId, Request $request): JsonResponse
    {
        $task = \App\Models\MarketingTask::findOrFail($taskId);
        $this->authorize('update', $task);

        $request->validate(['status' => 'required|string|in:new,in_progress,completed']);

        $task = $this->taskService->updateTaskStatus($taskId, $request->input('status'));

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'data' => $task
        ]);
    }
}
