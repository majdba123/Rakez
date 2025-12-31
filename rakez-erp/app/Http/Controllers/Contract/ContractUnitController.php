<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractUnitRequest;
use App\Http\Requests\Contract\UpdateContractUnitRequest;
use App\Http\Requests\Contract\UploadContractUnitsRequest;
use App\Http\Resources\Contract\ContractUnitResource;
use App\Services\Contract\ContractUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ContractUnitController extends Controller
{
    protected ContractUnitService $contractUnitService;

    public function __construct(ContractUnitService $contractUnitService)
    {
        $this->contractUnitService = $contractUnitService;
    }

    // رفع ملف CSV
    public function uploadCsvByContract(UploadContractUnitsRequest $request, int $contractId): JsonResponse
    {
        try {
            $result = $this->contractUnitService->uploadCsvByContractId(
                $contractId,
                $request->file('csv_file')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'status' => $result['status'],
                    'contract_id' => $result['contract_id'],
                    'second_party_data_id' => $result['second_party_data_id'],
                    'units_created' => $result['units_created'],
                ],
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // عرض الوحدات
    public function indexByContract(Request $request, int $contractId): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            $units = $this->contractUnitService->getUnitsByContractId($contractId, $perPage);

            return response()->json([
                'success' => true,
                'data' => ContractUnitResource::collection($units),
                'meta' => [
                    'current_page' => $units->currentPage(),
                    'last_page' => $units->lastPage(),
                    'per_page' => $units->perPage(),
                    'total' => $units->total(),
                ],
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // إضافة وحدة
    public function store(StoreContractUnitRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();
            $unit = $this->contractUnitService->addUnit($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الوحدة بنجاح',
                'data' => new ContractUnitResource($unit),
            ], 201);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // تعديل وحدة
    public function update(UpdateContractUnitRequest $request, int $unitId): JsonResponse
    {
        try {
            $data = $request->validated();
            $unit = $this->contractUnitService->updateUnit($unitId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تعديل الوحدة بنجاح',
                'data' => new ContractUnitResource($unit),
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // حذف وحدة
    public function destroy(int $unitId): JsonResponse
    {
        try {
            $this->contractUnitService->deleteUnit($unitId);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الوحدة بنجاح',
            ], 200);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    // معالجة الأخطاء
    private function errorResponse(Exception $e): JsonResponse
    {
        $statusCode = 500;
        if (str_contains($e->getMessage(), 'غير موجود')) $statusCode = 404;
        if (str_contains($e->getMessage(), 'غير مصرح')) $statusCode = 403;
        if (str_contains($e->getMessage(), 'يجب')) $statusCode = 422;

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $statusCode);
    }
}

