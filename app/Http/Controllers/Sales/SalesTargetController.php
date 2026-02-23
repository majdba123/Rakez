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
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'status' => $request->query('status'),
            'per_page' => ApiResponse::getPerPage($request, 15, 100),
        ];
        $targets = $this->targetService->getMyTargets($request->user(), $filters);
        return ApiResponse::success(
            SalesTargetResource::collection($targets->items()),
            'تم جلب الأهداف بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($targets)]
        );
    }

    /**
     * Get a single target by id.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $target = $this->targetService->getTarget($id, request()->user());
            return ApiResponse::success(new SalesTargetResource($target), 'تم جلب الهدف بنجاح');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ApiResponse::forbidden($e->getMessage());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('الهدف غير موجود');
        } catch (\Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * List team targets (leader only).
     */
    public function team(Request $request): JsonResponse
    {
        $filters = [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'status' => $request->query('status'),
            'marketer_id' => $request->query('marketer_id'),
            'per_page' => ApiResponse::getPerPage($request, 15, 100),
        ];
        $targets = $this->targetService->getTeamTargets($request->user(), $filters);
        return ApiResponse::success(
            SalesTargetResource::collection($targets->items()),
            'تم جلب أهداف الفريق بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($targets)]
        );
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
            return ApiResponse::created(new SalesTargetResource($target), 'تم إنشاء الهدف بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 400);
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
            return ApiResponse::success(new SalesTargetResource($target), 'تم تحديث الهدف بنجاح');
        } catch (\Exception $e) {
            $statusCode = $e->getMessage() === 'Unauthorized to update this target' ? 403 : 400;
            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }
}
