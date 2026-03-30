<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreTargetRequest;
use App\Http\Requests\Sales\UpdateTargetRequest;
use App\Http\Resources\Sales\SalesTargetProjectResource;
use App\Http\Resources\Sales\SalesTargetResource;
use App\Models\SalesTarget;
use App\Services\Sales\SalesTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesTargetController extends Controller
{
    public function __construct(
        private SalesTargetService $targetService
    ) {}

    /**
     * List my targets. Sales leader: projects assigned to their team. Sales: targets assigned to them by leader.
     */
    public function my(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
            ];

            $result = $this->targetService->getMyTargets($request->user(), $filters);
            $paginator = $result['paginator'];

            if ($result['type'] === \App\Services\Sales\SalesTargetService::MY_CONTENT_ASSIGNMENTS) {
                $data = SalesTargetProjectResource::collection($paginator->items());
            } else {
                $data = SalesTargetResource::collection($paginator->items());
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve targets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List targets for a project (units assigned to team + assignee per target). Allowed if user or their team has targets for this contract.
     */
    public function byProject(Request $request, int $contractId): JsonResponse
    {
        if (! Gate::forUser($request->user())->allows('viewTargetsByProject', $contractId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to targets for this project',
            ], 403);
        }
        try {
            $targets = $this->targetService->getTargetsByProject($contractId, $request->user());
            return response()->json([
                'success' => true,
                'data' => SalesTargetResource::collection($targets),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve targets: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new target (leader only).
     */
    public function store(StoreTargetRequest $request): JsonResponse
    {
        try {
            $target = $this->targetService->createTarget(
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Target created successfully',
                'data' => new SalesTargetResource($target),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create target: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update target status. The employee who owns the target (marketer_id) can set status to completed to mark as achieved (e.g. unit booked or sold).
     */
    public function update(int $id, UpdateTargetRequest $request): JsonResponse
    {
        $target = SalesTarget::findOrFail($id);
        $this->authorize('update', $target);

        try {
            $target = $this->targetService->updateTarget(
                $id,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Target updated successfully',
                'data' => new SalesTargetResource($target),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update target: ' . $e->getMessage(),
            ], 400);
        }
    }
}
