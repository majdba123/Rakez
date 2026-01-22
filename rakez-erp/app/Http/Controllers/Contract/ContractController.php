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
use Illuminate\Support\Facades\Auth;
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
            $filters = [
                'status' => $request->input('status'),
                'user_id' => Auth::id(),
                'city' => $request->input('city'),
                'district' => $request->input('district'),
                'project_name' => $request->input('project_name'),
            ];

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
            $contract = $this->contractService->getContractById($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقد بنجاح',
                'data' => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'Unauthorized') ? 403 : 404;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function show_editor(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقد بنجاح',
                'data' => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'Unauthorized') ? 403 : 404;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContract($id, $validated, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث العقد بنجاح',
                'data' => new ContractResource($contract->load('user', 'info'))
            ], 200);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Unauthorized')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 422);
        }
    }


    public function destroy(int $id): JsonResponse
    {
        try {
            $this->contractService->deleteContract($id, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'تم حذف العقد بنجاح'
            ], 200);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Unauthorized')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 403);
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 422);
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



    public function project_mange_index(Request $request): JsonResponse
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




    public function editor_index(Request $request): JsonResponse
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

    // ==========================================
    // PROJECT MANAGEMENT - Contract Teams APIs
    // ==========================================

    public function addTeamsToContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_ids' => 'required|array|min:1',
                'team_ids.*' => 'integer|exists:teams,id',
            ]);

            $contract = $this->contractService->attachTeamsToContract($contractId, $validated['team_ids']);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الفرق للعقد بنجاح',
                'data' => [
                    'contract_id' => $contract->id,
                    'teams' => $contract->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function removeTeamsFromContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_ids' => 'required|array|min:1',
                'team_ids.*' => 'integer|exists:teams,id',
            ]);

            $contract = $this->contractService->detachTeamsFromContract($contractId, $validated['team_ids']);

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الفرق من العقد بنجاح',
                'data' => [
                    'contract_id' => $contract->id,
                    'teams' => $contract->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function getTeamsForContract(int $contractId): JsonResponse
    {
        try {
            $teams = $this->contractService->getContractTeams($contractId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب فرق العقد بنجاح',
                'data' => $teams->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                ]),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
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


    public function getTeamsForContract_HR(int $contractId): JsonResponse
    {
        try {
            $teams = $this->contractService->getContractTeams($contractId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب فرق العقد بنجاح',
                'data' => $teams->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                ]),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

}
