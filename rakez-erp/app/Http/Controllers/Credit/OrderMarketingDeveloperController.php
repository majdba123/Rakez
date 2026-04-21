<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\IndexOrderMarketingDeveloperRequest;
use App\Http\Requests\Credit\StoreOrderMarketingDeveloperRequest;
use App\Http\Requests\Credit\UpdateOrderMarketingDeveloperRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Credit\OrderMarketingDeveloperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderMarketingDeveloperController extends Controller
{
    public function __construct(
        protected OrderMarketingDeveloperService $orderMarketingDeveloperService,
    ) {}

    /**
     * List marketing developer orders.
     * GET /credit/order-marketing-developers
     *
     * Query filters (all optional): id, developer_name, developer_number, description, location, status,
     * created_by, updated_by, created_from, created_to, updated_from, updated_to.
     * processed_by (user id): rows where that user created or last updated the record — allowed only for
     * admin or credit department manager; otherwise 403.
     * Credit employees always see only their own rows (created_by = current user); other filters apply within that scope.
     */
    public function index(IndexOrderMarketingDeveloperRequest $request): JsonResponse
    {
        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $paginator = $this->orderMarketingDeveloperService->list($perPage, $request->user(), $request->validated());

        $data = Collection::make($paginator->items())->map(
            fn ($row) => $this->orderMarketingDeveloperService->transform($row)
        );

        return response()->json([
            'success' => true,
            'message' => 'تم جلب السجلات بنجاح',
            'data' => $data->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 200);
    }

    /**
     * Create a marketing developer order row.
     * POST /credit/order-marketing-developers
     */
    public function store(StoreOrderMarketingDeveloperRequest $request): JsonResponse
    {
        $row = $this->orderMarketingDeveloperService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء السجل بنجاح',
            'data' => $this->orderMarketingDeveloperService->transform($row->load(['createdBy:id,name', 'updatedBy:id,name'])),
        ], 201);
    }

    /**
     * GET /credit/order-marketing-developers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $row = $this->orderMarketingDeveloperService->findForUser($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم جلب السجل بنجاح',
            'data' => $this->orderMarketingDeveloperService->transform($row),
        ], 200);
    }

    /**
     * PUT /credit/order-marketing-developers/{id}
     */
    public function update(UpdateOrderMarketingDeveloperRequest $request, int $id): JsonResponse
    {
        $row = $this->orderMarketingDeveloperService->findForUser($id, $request->user());
        $updated = $this->orderMarketingDeveloperService->update(
            $row,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السجل بنجاح',
            'data' => $this->orderMarketingDeveloperService->transform($updated),
        ], 200);
    }

    /**
     * DELETE /credit/order-marketing-developers/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->orderMarketingDeveloperService->delete($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السجل بنجاح',
        ], 200);
    }
}
