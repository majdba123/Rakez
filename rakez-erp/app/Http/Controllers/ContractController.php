<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Requests\UpdateContractStatusRequest;
use App\Models\Contract;
use App\Services\Contract\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ContractController extends Controller
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * Get all contracts with filters (for authenticated users - their own contracts)
     * GET /api/contracts
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'user_id' => auth()->id(),
                'city' => $request->query('city'),
                'district' => $request->query('district'),
                'project_name' => $request->query('project_name'),
                'units_count' => $request->query('units_count'),
                'unit_type' => $request->query('unit_type'),
                'total_units_value' => $request->query('total_units_value'),
                'average_unit_price' => $request->query('average_unit_price'),

            ];

            $perPage = $request->query('per_page', 15);

            $contracts = $this->contractService->getContracts($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => $contracts->items(),
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
     * Store a new contract
     * POST /api/contracts
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->storeContract($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح وحالته قيد الانتظار',
                'data' => $contract->load('user')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single contract by ID (authorized users only)
     * GET /api/contracts/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->getContractById($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقد بنجاح',
                'data' => $contract->load('user')
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'Unauthorized') ? 403 : 404;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Update a contract (only when status is pending and user owns it)
     * PUT /api/contracts/{id}
     */
    public function update(UpdateContractRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContract($id, $validated, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث العقد بنجاح',
                'data' => $contract->load('user')
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

    /**
     * Delete a contract (only when status is pending and user owns it)
     * DELETE /api/contracts/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->contractService->deleteContract($id, auth()->id());

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

    /**
     * Get all contracts for admin with filters
     * GET /api/admin/contracts
     */
    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->query('status'),
                'user_id' => $request->query('user_id'),
                'city' => $request->query('city'),
                'district' => $request->query('district'),
                'project_name' => $request->query('project_name'),
                'units_count' => $request->query('units_count'),
                'unit_type' => $request->query('unit_type'),
                'total_units_value' => $request->query('total_units_value'),
                'average_unit_price' => $request->query('average_unit_price'),
            ];

            $perPage = $request->query('per_page', 15);

            $contracts = $this->contractService->getContractsForAdmin($filters, (int) $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => $contracts->items(),
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
     * Update contract status (admin only)
     * PATCH /api/admin/contracts/{id}/status
     */
    public function adminUpdateStatus(UpdateContractStatusRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $contract = $this->contractService->updateContractStatus($id, $validated['status']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة العقد بنجاح',
                'data' => $contract->load('user')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Contract not found' ? 404 : 422);
        }
    }
}
