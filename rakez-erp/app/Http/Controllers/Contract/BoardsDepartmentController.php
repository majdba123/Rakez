<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreBoardsDepartmentRequest;
use App\Http\Requests\Contract\UpdateBoardsDepartmentRequest;
use App\Http\Resources\Contract\BoardsDepartmentResource;
use App\Services\Contract\BoardsDepartmentService;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * قسم اللوحات - Boards Department Controller
 */
class BoardsDepartmentController extends Controller
{
    protected BoardsDepartmentService $boardsDepartmentService;

    public function __construct(BoardsDepartmentService $boardsDepartmentService)
    {
        $this->boardsDepartmentService = $boardsDepartmentService;
    }

    /**
     * Store boards department data for a contract
     * حفظ بيانات قسم اللوحات
     */
    public function store(StoreBoardsDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $boardsDepartment = $this->boardsDepartmentService->store($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات قسم اللوحات بنجاح',
                'data' => new BoardsDepartmentResource($boardsDepartment),
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

    /**
     * Update boards department data for a contract
     * تحديث بيانات قسم اللوحات
     */
    public function update(UpdateBoardsDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $boardsDepartment = $this->boardsDepartmentService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات قسم اللوحات بنجاح',
                'data' => new BoardsDepartmentResource($boardsDepartment),
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

    /**
     * Show boards department data for a contract
     * عرض بيانات قسم اللوحات
     */
    public function show(int $contractId): JsonResponse
    {
        try {
            $boardsDepartment = $this->boardsDepartmentService->getByContractId($contractId);

            if (!$boardsDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات قسم اللوحات غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new BoardsDepartmentResource($boardsDepartment),
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

