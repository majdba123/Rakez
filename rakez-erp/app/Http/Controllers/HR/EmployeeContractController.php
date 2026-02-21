<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeContract;
use App\Services\HR\EmployeeContractService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class EmployeeContractController extends Controller
{
    protected EmployeeContractService $contractService;

    public function __construct(EmployeeContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * List contracts for an employee.
     * GET /hr/users/{id}/contracts
     */
    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $perPage = ApiResponse::getPerPage($request);
            $contracts = $this->contractService->getUserContracts($id, $perPage);

            $contractsData = $contracts->getCollection()->map(fn($c) => [
                'id' => $c->id,
                'start_date' => $c->start_date,
                'end_date' => $c->end_date,
                'status' => $c->status,
                'has_pdf' => !empty($c->pdf_path),
                'days_remaining' => $c->getRemainingDays(),
                'is_expiring_soon' => $c->isExpiringWithin(30),
                'created_at' => $c->created_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة العقود بنجاح',
                'data' => $contractsData->values()->all(),
                'meta' => [
                    'pagination' => [
                        'total' => $contracts->total(),
                        'count' => $contracts->count(),
                        'per_page' => $contracts->perPage(),
                        'current_page' => $contracts->currentPage(),
                        'total_pages' => $contracts->lastPage(),
                        'has_more_pages' => $contracts->hasMorePages(),
                    ],
                    'employee' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create a contract for an employee.
     * POST /hr/users/{id}/contracts
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'contract_data' => 'required|array',
            'contract_data.job_title' => 'required|string|max:100',
            'contract_data.department' => 'nullable|string|max:100',
            'contract_data.salary' => 'required|numeric|min:0',
            'contract_data.work_type' => 'nullable|string',
            'contract_data.probation_period' => 'nullable|string',
            'contract_data.terms' => 'nullable|string',
            'contract_data.benefits' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'nullable|in:draft,active',
        ]);

        try {
            User::findOrFail($id);
            $contract = $this->contractService->createContract($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء العقد بنجاح',
                'data' => [
                    'id' => $contract->id,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'status' => $contract->status,
                ],
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get contract details.
     * GET /hr/contracts/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $contract = EmployeeContract::with('employee')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل العقد بنجاح',
                'data' => [
                    'id' => $contract->id,
                    'employee' => [
                        'id' => $contract->employee->id,
                        'name' => $contract->employee->name,
                    ],
                    'contract_data' => $contract->contract_data,
                    'start_date' => $contract->start_date,
                    'end_date' => $contract->end_date,
                    'status' => $contract->status,
                    'has_pdf' => !empty($contract->pdf_path),
                    'pdf_path' => $contract->pdf_path,
                    'days_remaining' => $contract->getRemainingDays(),
                    'created_at' => $contract->created_at,
                    'updated_at' => $contract->updated_at,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Update a contract.
     * PUT /hr/contracts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'contract_data' => 'sometimes|array',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'sometimes|in:draft,active,expired,terminated',
        ]);

        try {
            $contract = $this->contractService->updateContract($id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث العقد بنجاح',
                'data' => [
                    'id' => $contract->id,
                    'status' => $contract->status,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Generate PDF for a contract.
     * POST /hr/contracts/{id}/pdf
     */
    public function generatePdf(int $id): JsonResponse
    {
        try {
            $pdfPath = $this->contractService->generatePdf($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء ملف PDF بنجاح',
                'data' => [
                    'pdf_path' => $pdfPath,
                    'download_url' => Storage::disk('public')->url($pdfPath),
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Download PDF for a contract.
     * GET /hr/contracts/{id}/pdf
     */
    public function downloadPdf(int $id)
    {
        try {
            $contract = EmployeeContract::findOrFail($id);

            if (empty($contract->pdf_path) || !Storage::disk('public')->exists($contract->pdf_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف PDF غير موجود',
                ], 404);
            }

            return Storage::disk('public')->download($contract->pdf_path);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Activate a contract.
     * POST /hr/contracts/{id}/activate
     */
    public function activate(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->activateContract($id);

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل العقد بنجاح',
                'data' => [
                    'id' => $contract->id,
                    'status' => $contract->status,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Terminate a contract.
     * POST /hr/contracts/{id}/terminate
     */
    public function terminate(int $id): JsonResponse
    {
        try {
            $contract = $this->contractService->terminateContract($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إنهاء العقد بنجاح',
                'data' => [
                    'id' => $contract->id,
                    'status' => $contract->status,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

