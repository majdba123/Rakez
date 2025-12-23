<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractInfoRequest;
use App\Http\Requests\Contract\UpdateContractInfoRequest;
use App\Services\Contract\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Exception;

class ContractInfoController extends Controller
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * Store contract info for a contract
     */
    public function store(StoreContractInfoRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check permission and eager-load relations to avoid extra queries
            $contract = $this->contractService->getContractById($contractId, auth()->id());

            // Only allow storing info if contract status is approved
            if ($contract->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'يمكن فقط حفظ بيانات العقد عندما تكون حالته موافق عليها',
                ], 422);
            }

            $info = $this->contractService->storeContractInfo($contractId, $data, $contract);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات العقد',
                'data' => $info->load('contract.user')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update contract info for a contract
     */
    public function update(UpdateContractInfoRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check permission and eager-load relations to avoid extra queries
            $contract = $this->contractService->getContractById($contractId, auth()->id());

            $info = $this->contractService->updateContractInfo($contractId, $data, $contract);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات العقد',
                'data' => $info->load('contract.user')
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
