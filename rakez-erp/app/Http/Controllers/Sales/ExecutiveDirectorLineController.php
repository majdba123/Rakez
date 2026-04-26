<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreExecutiveDirectorLineRequest;
use App\Http\Requests\Sales\UpdateExecutiveDirectorLineRequest;
use App\Http\Resources\Sales\ExecutiveDirectorLineResource;
use App\Http\Resources\Sales\SalesTargetResource;
use App\Models\ExecutiveDirectorLine;
use App\Services\Sales\SalesTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveDirectorLineController extends Controller
{
    public function __construct(
        private SalesTargetService $targetService
    ) {}

    /**
     * List sales targets where the assigner (leader) is an executive director.
     * Allowed only for employees with type = sales and is_manager = true (no extra route middleware/permission).
     * GET /api/sales/executive/targets
     */
    public function executiveTargets(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->isSalesTeamManager()) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - يجب أن تكون من نوع مبيعات ومدير (sales + is_manager).',
            ], 403);
        }

        try {
            $filters = [
                'from' => $request->query('from'),
                'to' => $request->query('to'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page', 15),
                'contract_id' => $request->query('contract_id'),
                'leader_id' => $request->query('leader_id'),
            ];

            $paginator = $this->targetService->listTargetsCreatedByExecutiveLeader($filters);

            return response()->json([
                'success' => true,
                'data' => SalesTargetResource::collection($paginator->items()),
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
                'message' => 'فشل جلب الأهداف: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all lines (no sales target).
     * GET /api/sales/executive-director-lines
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $rows = ExecutiveDirectorLine::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ExecutiveDirectorLineResource::collection($rows->items()),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/executive-director-lines
     * Body: line_type, value, status (status defaults to pending)
     */
    public function store(StoreExecutiveDirectorLineRequest $request): JsonResponse
    {
        $v = $request->validated();
        $row = ExecutiveDirectorLine::query()->create([
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
            'status' => $v['status'] ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()),
        ], 201);
    }

    /**
     * GET /api/sales/executive-director-lines/{id}
     */
    public function show(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ExecutiveDirectorLineResource($row),
        ]);
    }

    /**
     * PUT /api/sales/executive-director-lines/{id}
     * Body: line_type, value, status (status optional; omit to keep current)
     */
    public function update(UpdateExecutiveDirectorLineRequest $request, int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $v = $request->validated();
        $payload = [
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
        ];
        if (array_key_exists('status', $v) && $v['status'] !== null) {
            $payload['status'] = $v['status'];
        }
        $row->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()),
        ]);
    }

    /**
     * DELETE /api/sales/executive-director-lines/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السطر.',
        ]);
    }
}
