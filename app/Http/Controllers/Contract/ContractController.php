<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractRequest;
use App\Http\Requests\Contract\UpdateContractRequest;
use App\Http\Requests\Contract\UpdateContractStatusRequest;
use App\Http\Resources\Contract\ContractIndexResource;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Responses\ApiResponse;
use App\Models\Contract;
use App\Services\Contract\ContractService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
                return ApiResponse::unauthorized('غير مصرح - يرجى تسجيل الدخول');
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

            $perPage = ApiResponse::getPerPage($request, 15, 100);

            $contracts = $this->contractService->getContracts($filters, $perPage);

            $data = ContractIndexResource::collection($contracts->items())->resolve();
            return ApiResponse::success($data, 'تم جلب العقود بنجاح', 200, [
                'pagination' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'total_pages' => $contracts->lastPage(),
                    'has_more_pages' => $contracts->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $this->authorize('create', Contract::class);

        try {
            $validated = $request->validated();

            $contract = $this->contractService->storeContract($validated);

            return ApiResponse::created(new ContractResource($contract->load('user', 'info')), 'تم إنشاء العقد بنجاح وحالته قيد الانتظار');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }


    public function show(int $id): JsonResponse
    {
        try {
            // Fetch contract without service-level auth check
            $contract = $this->contractService->getContractById($id, null);

            // Enforce Policy
            $this->authorize('view', $contract);

            return ApiResponse::success(new ContractResource($contract), 'تم جلب العقد بنجاح');
        } catch (Exception $e) {
            $statusCode = $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : 404;
            return ApiResponse::error($e->getMessage(), $statusCode);
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

            return ApiResponse::success(new ContractResource($contract->load('user', 'info')), 'تم تحديث العقد بنجاح');
        } catch (Exception $e) {
            $statusCode = $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : ($e->getMessage() === 'Contract not found' ? 404 : 422);
            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }


    public function destroy(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, null);

            $this->authorize('delete', $contract);

            $this->contractService->deleteContract($id, null);

            return ApiResponse::success(null, 'تم حذف العقد بنجاح');
        } catch (Exception $e) {
            $statusCode = $e instanceof \Illuminate\Auth\Access\AuthorizationException ? 403 : ($e->getMessage() === 'Contract not found' ? 404 : 422);
            return ApiResponse::error($e->getMessage(), $statusCode);
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

            $perPage = ApiResponse::getPerPage($request, 15, 100);

            $contracts = $this->contractService->getContractsForAdmin($filters, $perPage);

            $data = ContractIndexResource::collection($contracts->items())->resolve();
            return ApiResponse::success($data, 'تم جلب العقود بنجاح', 200, [
                'pagination' => [
                    'total' => $contracts->total(),
                    'count' => $contracts->count(),
                    'per_page' => $contracts->perPage(),
                    'current_page' => $contracts->currentPage(),
                    'total_pages' => $contracts->lastPage(),
                    'has_more_pages' => $contracts->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
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

            $perPage = ApiResponse::getPerPage($request, 200, 500);

            $rows = $this->contractService->getContractLocationsForAdmin($filters, $perPage);

            $data = collect($rows->items())->map(function ($row) {
                return [
                    'contract_id' => (int) $row->contract_id,
                    'project_name' => $row->project_name,
                    'status' => $row->status,
                    'lat' => $row->lat !== null ? (float) $row->lat : null,
                    'lng' => $row->lng !== null ? (float) $row->lng : null,
                ];
            })->values()->all();
            return ApiResponse::success($data, 'تم جلب المواقع بنجاح', 200, [
                'pagination' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'total_pages' => $rows->lastPage(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
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

            $perPage = ApiResponse::getPerPage($request, 50, 200);

            $rows = $this->contractService->getContractsAgencyOverviewForAdmin($filters, $perPage);

            $contractIds = collect($rows->items())->pluck('contract_id')->map(fn($v) => (int) $v)->all();

            // Aggregate units: sum(count) by contract_id / status / unit_type
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
                        DB::raw('SUM(contract_units.count) as total_count'),
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

            return ApiResponse::success($data, 'تم جلب بيانات المخزون بنجاح', 200, [
                'pagination' => [
                    'total' => $rows->total(),
                    'count' => $rows->count(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'total_pages' => $rows->lastPage(),
                    'has_more_pages' => $rows->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }


    public function adminUpdateStatus(UpdateContractStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContractStatus($id, $validated['status']);

            return ApiResponse::success(new ContractResource($contract->load('user', 'info')), 'تم تحديث حالة العقد بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), $e->getMessage() === 'Contract not found' ? 404 : 422);
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

            return ApiResponse::success(new ContractResource($contract->load('user', 'info', 'secondPartyData')), 'تم تحديث حالة العقد بنجاح');
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 422;
            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Get teams assigned to a contract (project_management).
     * GET /api/project_management/teams/index/{contractId}
     * Response: { success, data: [ { id, name, description }, ... ] } for "الفرق المعينة حالياً"
     */
    public function getTeamsForContract_HR(int $contractId): JsonResponse
    {
        try {
            $teams = $this->contractService->getContractTeams($contractId);
            $list = $teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
            ])->values()->all();
            return ApiResponse::success($list);
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 500;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * Add one or multiple teams to a contract (project_management).
     * POST /api/project_management/teams/add/{contractId}
     * Body: { "team_ids": [1, 2, 3] } for multiple, or { "team_id": 5 } for single.
     */
    public function addTeamsToContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $teamIds = $request->input('team_ids');
            if ($teamIds === null && $request->has('team_id')) {
                $teamIds = [$request->input('team_id')];
            }
            $request->merge(['team_ids' => $teamIds]);

            $request->validate([
                'team_ids' => 'required|array|min:1',
                'team_ids.*' => 'integer|exists:teams,id',
            ], [
                'team_ids.required' => 'يجب اختيار فريق واحد على الأقل',
                'team_ids.min' => 'يجب اختيار فريق واحد على الأقل',
                'team_ids.*.exists' => 'الفريق المحدد غير موجود',
            ]);

            $contract = $this->contractService->attachTeamsToContract($contractId, $request->input('team_ids'));

            $teams = $contract->load('teams')->teams;
            $data = $teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'description' => $t->description])->values()->all();

            $msg = count($teamIds) > 1 ? 'تم تعيين الفرق بنجاح' : 'تم تعيين الفريق بنجاح';
            return ApiResponse::success($data, $msg);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError($e->errors(), 'بيانات غير صالحة');
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 500;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }

    /**
     * Remove teams from a contract (project_management).
     * POST /api/project_management/teams/remove/{contractId}
     * Body: { "team_ids": [1, 2] }
     */
    public function removeTeamsFromContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $request->validate([
                'team_ids' => 'required|array',
                'team_ids.*' => 'integer|exists:teams,id',
            ], [
                'team_ids.required' => 'يجب اختيار فريق واحد على الأقل',
            ]);

            $contract = $this->contractService->detachTeamsFromContract($contractId, $request->input('team_ids'));

            return ApiResponse::success($contract->load('teams'), 'تم إلغاء تعيين الفريق بنجاح');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validationError($e->errors(), 'بيانات غير صالحة');
        } catch (Exception $e) {
            $code = str_contains($e->getMessage(), 'not found') ? 404 : 500;
            return ApiResponse::error($e->getMessage(), $code);
        }
    }
}
