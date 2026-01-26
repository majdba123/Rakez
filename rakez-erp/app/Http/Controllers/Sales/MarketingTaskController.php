<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreMarketingTaskRequest;
use App\Http\Requests\Sales\UpdateMarketingTaskRequest;
use App\Http\Resources\Sales\MarketingTaskResource;
use App\Models\Contract;
use App\Services\Sales\MarketingTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingTaskController extends Controller
{
    public function __construct(
        private MarketingTaskService $taskService
    ) {}

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
            $project = Contract::with(['montageDepartment'])->findOrFail($contractId);

            $data = [
                'contract_id' => $project->id,
                'project_name' => $project->project_name,
                'project_description' => $project->notes ?? $project->project_description ?? '',
                'montage_designs' => [
                    'image_url' => $project->montageDepartment->image_url ?? null,
                    'video_url' => $project->montageDepartment->video_url ?? null,
                    'description' => $project->montageDepartment->description ?? null,
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
}
