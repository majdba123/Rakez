<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreSecondPartyDataRequest;
use App\Http\Requests\Contract\UpdateSecondPartyDataRequest;
use App\Http\Resources\Contract\SecondPartyDataResource;
use App\Services\Contract\SecondPartyDataService;
use Illuminate\Http\JsonResponse;
use Exception;

class SecondPartyDataController extends Controller
{
    protected SecondPartyDataService $secondPartyDataService;

    public function __construct(SecondPartyDataService $secondPartyDataService)
    {
        $this->secondPartyDataService = $secondPartyDataService;
    }

    /**
     * Store second party data for a contract
     * Only one record per contract is allowed
     *
     * POST /api/contracts/{contractId}/second-party-data
     */
    public function store(StoreSecondPartyDataRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $secondPartyData = $this->secondPartyDataService->store($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات الطرف الثاني بنجاح',
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'موجودة بالفعل') ? 422 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;
            $statusCode = str_contains($e->getMessage(), 'يجب أن يكون') ? 422 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function update(UpdateSecondPartyDataRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $secondPartyData = $this->secondPartyDataService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الطرف الثاني بنجاح',
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجودة') ? 404 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


    public function show(int $contractId): JsonResponse
    {
        try {
            $secondPartyData = $this->secondPartyDataService->getByContractId($contractId);

            if (!$secondPartyData) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات الطرف الثاني غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SecondPartyDataResource($secondPartyData),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


}

