<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingSalaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AccountingSalaryController extends Controller
{
    protected AccountingSalaryService $salaryService;

    public function __construct(AccountingSalaryService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    /**
     * Get employee salaries with commissions.
     * GET /api/accounting/salaries
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2100',
                'type' => 'nullable|string',
                'team_id' => 'nullable|exists:teams,id',
                'commission_eligible' => 'nullable|boolean',
            ]);

            $month = (int) $request->input('month');
            $year = (int) $request->input('year');
            $filters = $request->only(['type', 'team_id', 'commission_eligible']);

            $salaries = $this->salaryService->getSalariesWithCommissions($month, $year, $filters);
            $data = is_array($salaries) ? $salaries : $salaries->values()->all();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الرواتب بنجاح',
                'data' => $data,
                'count' => count($data),
                'period' => [
                    'month' => $month,
                    'year' => $year,
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee detail with sold units.
     * GET /api/accounting/salaries/{userId}
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            $month = $request->input('month');
            $year = $request->input('year');

            $employeeData = $this->salaryService->getEmployeeSoldUnits($userId, $month, $year);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الموظف بنجاح',
                'data' => $employeeData,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Create salary distribution for employee.
     * POST /api/accounting/salaries/{userId}/distribute
     */
    public function createDistribution(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:2100',
            ]);

            $month = $request->input('month');
            $year = $request->input('year');

            $distribution = $this->salaryService->createSalaryDistribution($userId, $month, $year);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء توزيع الراتب بنجاح',
                'data' => $distribution,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Approve salary distribution.
     * POST /api/accounting/salaries/distributions/{distributionId}/approve
     */
    public function approveDistribution(Request $request, int $distributionId): JsonResponse
    {
        try {
            $distribution = $this->salaryService->approveSalaryDistribution($distributionId);

            return response()->json([
                'success' => true,
                'message' => 'تم الموافقة على توزيع الراتب بنجاح',
                'data' => $distribution,
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Mark salary distribution as paid.
     * POST /api/accounting/salaries/distributions/{distributionId}/paid
     */
    public function markAsPaid(Request $request, int $distributionId): JsonResponse
    {
        try {
            $distribution = $this->salaryService->markSalaryDistributionAsPaid($distributionId);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديد الراتب كمدفوع بنجاح',
                'data' => $distribution,
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
