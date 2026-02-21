<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreTargetRequest;
use App\Http\Requests\Sales\UpdateTargetRequest;
use App\Http\Resources\Sales\SalesTargetResource;
use App\Http\Responses\ApiResponse;
use App\Services\Sales\SalesTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTargetController extends Controller
{
    public function __construct(
        private SalesTargetService $targetService
    ) {}

    /**
     * List my targets.
     */
    public function my(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'status' => $request->query('status'),
                'per_page' => ApiResponse::getPerPage($request, 15, 100),
            ];

            $targets = $this->targetService->getMyTargets($request->user(), $filters);

            return response()->json([
                'success' => true,
                'data' => SalesTargetResource::collection($targets->items()),
                'meta' => [
                    'current_page' => $targets->currentPage(),
                    'last_page' => $targets->lastPage(),
                    'per_page' => $targets->perPage(),
                    'total' => $targets->total(),
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
     * Get a single target by id.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $target = $this->targetService->getTarget($id, request()->user());

            return response()->json([
                'success' => true,
                'data' => new SalesTargetResource($target),
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Target not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve target: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List team targets (leader only).
     */
    public function team(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'status' => $request->query('status'),
                'marketer_id' => $request->query('marketer_id'),
                'per_page' => ApiResponse::getPerPage($request, 15, 100),
            ];

            $targets = $this->targetService->getTeamTargets($request->user(), $filters);

            return response()->json([
                'success' => true,
                'data' => SalesTargetResource::collection($targets->items()),
                'meta' => [
                    'current_page' => $targets->currentPage(),
                    'last_page' => $targets->lastPage(),
                    'per_page' => $targets->perPage(),
                    'total' => $targets->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team targets: ' . $e->getMessage(),
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
     * Update target status.
     */
    public function update(int $id, UpdateTargetRequest $request): JsonResponse
    {
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
            $statusCode = $e->getMessage() === 'Unauthorized to update this target' ? 403 : 400;
            return response()->json([
                'success' => false,
                'message' => 'Failed to update target: ' . $e->getMessage(),
            ], $statusCode);
        }
    }
}
