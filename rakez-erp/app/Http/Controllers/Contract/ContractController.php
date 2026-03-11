<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Http\Requests\Contract\UpdateContractStatusRequest;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Models\Contract;
use App\Services\Contract\ContractService;
use App\Services\Contract\InventoryAgencyOverviewService;
use App\Services\Contract\InventoryDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ContractController extends Controller
{
    public function __construct(
        protected ContractService $contractService,
        protected InventoryAgencyOverviewService $inventoryAgencyOverviewService,
        protected InventoryDashboardService $inventoryDashboardService
    ) {
    }


    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح - يرجى تسجيل الدخول',
                ], 401);
            }
            $filters = [
                'status' => $request->input('status'),
                'city' => $request->input('city'),
                'district' => $request->input('district'),
                'project_name' => $request->input('project_name'),
            ];

            // Apply access control filters
            if ($user->can('contracts.view_all')) {
                // Can view all, no user filter enforced
            } elseif ($user->isManager() && $user->team) {
                // Manager sees team contracts
                // Note: Service needs to support team filtering.
                // For now, we'll filter by the manager's ID to avoid breaking,
                // but ideally this should pass the team or list of user IDs.
                // Assuming for now we default to own contracts if service doesn't support team yet.
                $filters['user_id'] = $user->id;
            } else {
                // Regular user sees only their own
                $filters['user_id'] = $user->id;
            }

            $perPage = $request->input('per_page', 15);

            $contracts = $this->contractService->getContracts($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractIndexResource::collection($contracts->items()),
                'meta' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        try {
            $validated = $request->validated();

            $contract = $this->contractService->storeContract($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح وحالته قيد الانتظار',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Inventory dashboard: marketing projects count, units stats, pending and closed contracts KPIs.
     * Query params: include_pending_count, include_closed_count (default true).
     */
    public function inventoryDashboard(Request $request): JsonResponse
    {
        try {
            $includePendingCount = $request->boolean('include_pending_count', true);
            $includeClosedCount = $request->boolean('include_closed_count', true);

            $data = $this->inventoryDashboardService->getDashboardData([
                'include_pending_count' => $includePendingCount,
                'include_closed_count' => $includeClosedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة التحكم للمخزون بنجاح',
                'data' => $data,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            // Fetch contract without service-level auth check
            $contract = $this->contractService->getContractById($id, null);

            // Enforce Policy
            $this->authorize('view', $contract);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقد بنجاح',
                'data' => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = 404;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }



    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            // Fetch contract to authorize
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('update', $contract);

            $validated = $request->validated();

            // Pass null for userId to skip service auth check since we already authorized
            $contract = $this->contractService->updateContract($id, $validated, null);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            } elseif ($e->getMessage() === 'Contract not found') {
                $statusCode = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function destroy(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('delete', $contract);

            $this->contractService->deleteContract($id, null);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العقد بنجاح'
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $statusCode = 403;
            } elseif ($e->getMessage() === 'Contract not found') {
                $statusCode = 404;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city' => $request->input('city'),
                'district' => $request->input('district'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = $request->input('per_page', 15);

            $contracts = $this->contractService->getContractsForAdmin($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractIndexResource::collection($contracts->items()),
                'meta' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function locations(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city' => $request->input('city'),
                'district' => $request->input('district'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = (int) $request->input('per_page', 200);
            $perPage = max(1, min($perPage, 500));

            $rows = $this->contractService->getContractLocationsForAdmin($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المواقع بنجاح',
                'data' => collect($rows->items())->map(function ($row) {
                    return [
                        'contract_id' => (int) $row->contract_id,
                        'project_name' => $row->project_name,
                        'status' => $row->status,
                        'location_url' => $row->location_url,
                    ];
                }),
                'meta' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Inventory/Admin: contracts list for inventory dashboard.
     * Delegates to InventoryAgencyOverviewService for data and meta.
     */
    public function inventoryAgencyOverview(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'user_id' => $request->input('user_id'),
                'city' => $request->input('city'),
                'district' => $request->input('district'),
                'project_name' => $request->input('project_name'),
                'has_photography' => $request->input('has_photography'),
                'has_montage' => $request->input('has_montage'),
            ];

            $perPage = (int) $request->input('per_page', 50);
            $perPage = max(1, min($perPage, 200));

            $result = $this->inventoryAgencyOverviewService->getOverviewData($filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات المخزون بنجاح',
                'data' => $result['data'],
                'meta' => $result['meta'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function adminUpdateStatus(UpdateContractStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContractStatus($id, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 422);
        }
    }

    public function projectManagementUpdateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:ready,rejected',
            ], [
                'status.required' => 'الحالة مطلوبة',
                'status.in' => 'الحالة يجب أن تكون: ready أو rejected',
            ]);

            $contract = $this->contractService->updateContractStatusByProjectManagement($id, $request->status);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info', 'secondPartyData'))
            ], 200);
        } catch (Exception $e) {
            $statusCode = 422;
            if (str_contains($e->getMessage(), 'غير موجود')) $statusCode = 404;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get teams assigned to a contract.
     * GET /hr/teams/getTeamsForContract/{contractId}
     */
    public function getTeamsForContract(int $contractId): JsonResponse
    {
        try {
            $teams = $this->contractService->getContractTeams($contractId);

            return response()->json([
                'success' => true,
                'data' => $teams,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'No query results') || str_contains($e->getMessage(), 'Contract not found') ? 404 : 500);
        }
    }

    /**
     * Get teams assigned to a contract (project_management context).
     * GET /project_management/teams/index/{contractId}
     */
    public function getTeamsForContract_HR(int $contractId): JsonResponse
    {
        return $this->getTeamsForContract($contractId);
    }
}
