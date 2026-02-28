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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class ContractController extends Controller
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
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

    /**
     * Inventory/Admin: return only contract locations with adminIndex-like filters.
     */
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
                        'lat' => $row->lat !== null ? (float) $row->lat : null,
                        'lng' => $row->lng !== null ? (float) $row->lng : null,
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
     *
     * Returns only:
     * - color (based on remaining time until agency_date)
     * - contract_id, project_name, status, lat, lng
     * - agency_date
     * - units_stats: counts grouped by unit status and unit type (sum of `count`)
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

            $rows = $this->contractService->getContractsAgencyOverviewForAdmin($filters, $perPage);

            $contractIds = collect($rows->items())->pluck('contract_id')->map(fn($v) => (int) $v)->all();

            // Aggregate units: count rows by contract_id / status / unit_type (each row is one unit; count column was removed)
            $unitAgg = [];
            if (!empty($contractIds)) {
                $unitRows = DB::table('contract_units')
                    ->join('second_party_data', 'second_party_data.id', '=', 'contract_units.second_party_data_id')
                    ->whereNull('contract_units.deleted_at')
                    ->whereNull('second_party_data.deleted_at')
                    ->whereIn('second_party_data.contract_id', $contractIds)
                    ->groupBy('second_party_data.contract_id', 'contract_units.status', 'contract_units.unit_type')
                    ->select([
                        'second_party_data.contract_id as contract_id',
                        'contract_units.status as unit_status',
                        'contract_units.unit_type as unit_type',
                        DB::raw('COUNT(contract_units.id) as total_count'),
                    ])
                    ->get();

                foreach ($unitRows as $r) {
                    $cid = (int) $r->contract_id;
                    $status = (string) ($r->unit_status ?? 'unknown');
                    $type = (string) ($r->unit_type ?? 'unknown');
                    $count = (int) $r->total_count;

                    $unitAgg[$cid]['total'] = ($unitAgg[$cid]['total'] ?? 0) + $count;
                    $unitAgg[$cid]['by_status'][$status]['total'] = ($unitAgg[$cid]['by_status'][$status]['total'] ?? 0) + $count;
                    $unitAgg[$cid]['by_status'][$status]['by_type'][$type] = ($unitAgg[$cid]['by_status'][$status]['by_type'][$type] ?? 0) + $count;
                }
            }

            $data = collect($rows->items())->map(function ($row) use ($unitAgg) {
                $agencyDate = $row->agency_date ? Carbon::parse($row->agency_date) : null;
                $remainingDays = $agencyDate ? now()->diffInDays($agencyDate, false) : null;

                $color = null;
                if ($remainingDays !== null) {
                    if ($remainingDays > 90) {
                        $color = 'green';
                    } elseif ($remainingDays > 30) {
                        $color = 'yellow';
                    } else {
                        $color = 'red';
                    }
                }

                $cid = (int) $row->contract_id;

                return [
                    'contract_id' => $cid,
                    'project_name' => $row->project_name,
                    'status' => $row->status,
                    'lat' => $row->lat !== null ? (float) $row->lat : null,
                    'lng' => $row->lng !== null ? (float) $row->lng : null,
                    'agency_date' => $agencyDate?->toDateString(),
                    'color' => $color,
                    'units_stats' => $unitAgg[$cid] ?? [
                        'total' => 0,
                        'by_status' => [],
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات المخزون بنجاح',
                'data' => $data,
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

    /**
     * Update contract status by Project Management
     * Can set status to 'ready' or 'rejected'
     */
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
}
