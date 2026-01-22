<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StorePhotographyDepartmentRequest;
use App\Http\Requests\Contract\UpdatePhotographyDepartmentRequest;
use App\Http\Resources\Contract\PhotographyDepartmentResource;
use App\Services\Contract\PhotographyDepartmentService;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * قسم التصوير - Photography Department Controller
 */
class PhotographyDepartmentController extends Controller
{
    protected PhotographyDepartmentService $photographyDepartmentService;

    public function __construct(PhotographyDepartmentService $photographyDepartmentService)
    {
        $this->photographyDepartmentService = $photographyDepartmentService;
    }

    /**
     * Store photography department data for a contract
     * حفظ بيانات قسم التصوير
     */
    public function store(StorePhotographyDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $photographyDepartment = $this->photographyDepartmentService->store($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ بيانات قسم التصوير بنجاح',
                'data' => new PhotographyDepartmentResource($photographyDepartment),
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
     * Update photography department data for a contract
     * تحديث بيانات قسم التصوير
     */
    public function update(UpdatePhotographyDepartmentRequest $request, int $contractId): JsonResponse
    {
        try {
            $data = $request->validated();

            $photographyDepartment = $this->photographyDepartmentService->updateByContractId($contractId, $data);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات قسم التصوير بنجاح',
                'data' => new PhotographyDepartmentResource($photographyDepartment),
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
     * Show photography department data for a contract
     * عرض بيانات قسم التصوير
     */
    public function show(int $contractId): JsonResponse
    {
        try {
            $photographyDepartment = $this->photographyDepartmentService->getByContractId($contractId);

            if (!$photographyDepartment) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات قسم التصوير غير موجودة لهذا العقد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new PhotographyDepartmentResource($photographyDepartment),
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير مصرح') ? 403 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Approve photography department (project_management manager only)
     */
    public function approve(int $contractId): JsonResponse
    {
        try {
            $photographyDepartment = $this->photographyDepartmentService->approveByContractId($contractId);

            return response()->json([
                'success' => true,
                'message' => 'تم اعتماد بيانات قسم التصوير بنجاح',
                'data' => new PhotographyDepartmentResource($photographyDepartment),
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
}

