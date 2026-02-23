<?php

namespace App\Http\Controllers\Sales;

/**
 * Sales context: leader task management for sales projects (assign tasks to marketers, view by project).
 * For marketing-module daily tasks see App\Http\Controllers\Marketing\MarketingTaskController.
 */
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreMarketingTaskRequest;
use App\Http\Requests\Sales\UpdateMarketingTaskRequest;
use App\Http\Resources\Sales\MarketingTaskResource;
use App\Http\Resources\Sales\TaskProjectDetailResource;
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
        $filters = [
            'status' => $request->query('status'),
            'contract_id' => $request->query('contract_id'),
            'marketer_id' => $request->query('marketer_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'per_page' => ApiResponse::getPerPage($request, 15, 100),
        ];
        $tasks = $this->taskService->listTasksForLeader($request->user(), $filters);

        return ApiResponse::success(
            MarketingTaskResource::collection($tasks->items()),
            'تم جلب قائمة المهام بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($tasks)]
        );
    }

    /**
     * List projects for task management (leader only).
     */
    public function projects(Request $request): JsonResponse
    {
        $projects = $this->taskService->getTaskProjects($request->user());
        return ApiResponse::success($projects, 'تم جلب قائمة المشاريع بنجاح');
    }

    /**
     * Show project details for task management (leader only).
     */
    public function showProject(Request $request, int $contractId): JsonResponse
    {
        try {
            $project = $this->taskService->getProjectForTask($contractId, $request->user());
            return ApiResponse::success(new TaskProjectDetailResource($project), 'تم جلب بيانات المشروع بنجاح');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('المشروع غير موجود');
        } catch (\Exception $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * Get a single marketing task by id (leader only).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTask($id, request()->user());
            return ApiResponse::success(new MarketingTaskResource($task), 'تم جلب المهمة بنجاح');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('المهمة غير موجودة');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
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
            return ApiResponse::created(
                new MarketingTaskResource($task),
                'تم إنشاء المهمة التسويقية بنجاح'
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
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
            return ApiResponse::success(
                new MarketingTaskResource($task),
                'تم تحديث المهمة بنجاح'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('المهمة غير موجودة');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Delete a marketing task (leader only).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->taskService->deleteTask($id, request()->user());
            return ApiResponse::success(null, 'تم حذف المهمة بنجاح');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('المهمة غير موجودة');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }
}
