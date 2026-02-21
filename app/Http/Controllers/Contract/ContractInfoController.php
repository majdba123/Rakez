<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractInfoRequest;
use App\Http\Requests\Contract\UpdateContractInfoRequest;
use App\Http\Resources\Contract\ContractResource;
use App\Http\Resources\Contract\ContractInfoResource;
use App\Models\ContractInfo;
use App\Models\Contract;
use App\Http\Responses\ApiResponse;
use App\Services\Contract\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            // Prevent creating a new ContractInfo if one already exists
            if ($contract->info) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات العقد موجودة بالفعل ولا يمكن إنشاؤها مرة أخرى',
                ], 422);
            }

            // Only allow storing info if contract status is approved
            if ($contract->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'يمكن فقط حفظ بيانات العقد عندما تكون حالته موافق عليها',
                ], 422);
            }

            $info = $this->contractService->storeContractInfo($contractId, $data, $contract);

            // Change contract status to complete
            $contract->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات العقد',
                'data' => new ContractInfoResource($info->load('contract.user'))
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
                'data' => new ContractInfoResource($info->load('contract.user'))
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all unique second parties (no duplicates by email)
     * جلب جميع الأطراف الثانية بدون تكرار حسب الإيميل
     */
    public function getAllSecondParties(Request $request): JsonResponse
    {
        try {
            $perPage = ApiResponse::getPerPage($request, 15, 100);

            // Get unique second parties by email (take latest info for each email)
            $secondParties = ContractInfo::whereNotNull('second_party_email')
                ->where('second_party_email', '!=', '')
                ->selectRaw('
                    second_party_email,
                    MAX(second_party_name) as second_party_name,
                    MAX(second_party_phone) as second_party_phone,
                    MAX(second_party_address) as second_party_address,
                    MAX(second_party_cr_number) as second_party_cr_number,
                    MAX(second_party_signatory) as second_party_signatory,
                    MAX(second_party_id_number) as second_party_id_number,
                    MAX(second_party_role) as second_party_role,
                    COUNT(*) as contracts_count
                ')
                ->groupBy('second_party_email')
                ->orderBy('second_party_name')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الأطراف الثانية بنجاح',
                'data' => $secondParties->items(),
                'meta' => [
                    'total' => $secondParties->total(),
                    'count' => $secondParties->count(),
                    'per_page' => $secondParties->perPage(),
                    'current_page' => $secondParties->currentPage(),
                    'last_page' => $secondParties->lastPage(),
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
     * Get all contracts by second party email
     * جلب جميع العقود حسب إيميل الطرف الثاني
     */
    public function getContractsBySecondPartyEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->input('email');
            $perPage = ApiResponse::getPerPage($request, 15, 100);

            $contracts = Contract::whereHas('info', function ($query) use ($email) {
                $query->where('second_party_email', $email);
            })
            ->with(['user', 'info'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب العقود بنجاح',
                'data' => ContractResource::collection($contracts),
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
}
