<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
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

    /**
     * Upload CSV file to create contract units
     * Only one CSV upload is allowed per SecondPartyData
     *
     * POST /api/second-party-data/{secondPartyDataId}/units/upload-csv
     */
    public function uploadCsv(UploadContractUnitsRequest $request, int $secondPartyDataId): JsonResponse
    {
        try {
            $result = $this->contractUnitService->uploadCsv(
                $secondPartyDataId,
                $request->file('csv_file')
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'status' => $result['status'],
                    'second_party_data_id' => $result['second_party_data_id'],
                ],
            ], 202); // 202 Accepted - processing in background
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'مسبقاً') ? 422 : 500;
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : $statusCode;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get all units for a SecondPartyData
     *
     * GET /api/second-party-data/{secondPartyDataId}/units
     */
    public function index(Request $request, int $secondPartyDataId): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 15);
            $units = $this->contractUnitService->getUnitsBySecondPartyDataId($secondPartyDataId, $perPage);

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
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }


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
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get a single unit by ID
     *
     * GET /api/units/{unitId}
     */
    public function show(int $unitId): JsonResponse
    {
        try {
            $unit = $this->contractUnitService->getUnitById($unitId);

            return response()->json([
                'success' => true,
                'data' => new ContractUnitResource($unit),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'الوحدة غير موجودة',
            ], 404);
        }
    }

    /**
     * Update a unit by ID
     *
     * PUT /api/units/{unitId}
     */
    public function update(UpdateContractUnitRequest $request, int $unitId): JsonResponse
    {
        try {
            $data = $request->validated();
            $unit = $this->contractUnitService->updateUnit($unitId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الوحدة بنجاح',
                'data' => new ContractUnitResource($unit),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجود') ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get units statistics for a SecondPartyData
     *
     * GET /api/second-party-data/{secondPartyDataId}/units/stats
     */
    public function stats(int $secondPartyDataId): JsonResponse
    {
        try {
            $stats = $this->contractUnitService->getUnitsStats($secondPartyDataId);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

