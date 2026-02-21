<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreMarketingTaskRequest;
use App\Http\Requests\Sales\UpdateMarketingTaskRequest;
use App\Http\Resources\Sales\MarketingTaskResource;
use App\Models\Contract;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\MarketingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingTaskController extends Controller
{
    public function __construct(
        private MarketingTaskService $taskService
    ) {}

    /**
     * List marketing tasks for the current leader (created by them or for their projects).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'contract_id' => $request->query('contract_id'),
                'marketer_id' => $request->query('marketer_id'),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'per_page' => ApiResponse::getPerPage($request, 15, 100),
            ];
            $tasks = $this->taskService->listTasksForLeader($request->user(), $filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة المهام بنجاح',
                'data' => MarketingTaskResource::collection($tasks->items()),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List projects for task management (leader only).
     */
    public function projects(Request $request): JsonResponse
    {
        try {
            $projects = $this->taskService->listTaskProjects($request->user());

            return response()->json([
                'success' => true,
                'data' => $projects,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show project details for task management (leader only).
     */
    public function showProject(int $contractId): JsonResponse
    {
        try {
            $project = Contract::with(['montageDepartment', 'photographyDepartment', 'boardsDepartment'])->findOrFail($contractId);

            $data = [
                'contract_id' => $project->id,
                'project_name' => $project->project_name,
                'project_description' => $project->notes ?? $project->project_description ?? '',
                'montage_designs' => [
                    'image_url' => $project->montageDepartment->image_url ?? null,
                    'video_url' => $project->montageDepartment->video_url ?? null,
                    'description' => $project->montageDepartment->description ?? null,
                ],
                'photography' => [
                    'image_url' => $project->photographyDepartment->image_url ?? null,
                    'video_url' => $project->photographyDepartment->video_url ?? null,
                    'description' => $project->photographyDepartment->description ?? null,
                ],
                'boards' => [
                    'image_url' => $project->boardsDepartment->image_url ?? null,
                    'description' => $project->boardsDepartment->description ?? null,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get a single marketing task by id (leader only).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTask($id, request()->user());

            return response()->json([
                'success' => true,
                'data' => new MarketingTaskResource($task),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new marketing task (leader only).
     */
    public function store(StoreMarketingTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->createTask(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Marketing task created successfully',
                'data' => new MarketingTaskResource($task),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update marketing task status (leader only).
     */
    public function update(int $id, UpdateMarketingTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->updateTask(
                $id,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => new MarketingTaskResource($task),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a marketing task (leader only).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->taskService->deleteTask($id, request()->user());

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully',
            ], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task: ' . $e->getMessage(),
            ], 500);
        }
    }
}
