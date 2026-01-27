<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreMontageDepartmentRequest;
use App\Http\Requests\Contract\UpdateMontageDepartmentRequest;
use App\Http\Resources\Contract\MontageDepartmentResource;
use App\Services\Contract\MontageDepartmentService;
use App\Events\Marketing\ImageUploadedEvent;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * قسم المونتاج - Montage Department Controller
 */
class MontageDepartmentController extends Controller
{
    protected MontageDepartmentService $montageDepartmentService;

    public function __construct(MontageDepartmentService $montageDepartmentService)
    {
        $this->montageDepartmentService = $montageDepartmentService;
    }

    public function store(StoreMontageDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $montageDepartment = $this->montageDepartmentService->store($contractId, $data);

            if (isset($data['image_url'])) {
                event(new ImageUploadedEvent($contractId, $data['image_url'], 'montage'));
            }

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات قسم المونتاج بنجاح',
                'data' => new MontageDepartmentResource($montageDepartment),
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

    public function update(UpdateMontageDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $montageDepartment = $this->montageDepartmentService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات قسم المونتاج بنجاح',
                'data' => new MontageDepartmentResource($montageDepartment),
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
            $montageDepartment = $this->montageDepartmentService->getByContractId($contractId);

            if (!$montageDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات قسم المونتاج غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new MontageDepartmentResource($montageDepartment),
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

