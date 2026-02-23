<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Accounting\AccountingDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class AccountingDashboardController extends Controller
{
    protected AccountingDashboardService $dashboardService;

    public function __construct(AccountingDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get accounting dashboard metrics.
     * GET /api/accounting/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return ApiResponse::unauthorized();
        }
        try {
            $request->validate([
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
            ]);

            $metrics = $this->dashboardService->getDashboardMetrics(
                $request->input('from_date'),
                $request->input('to_date')
            );

            return ApiResponse::success($metrics, 'تم جلب بيانات لوحة المحاسبة بنجاح');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
