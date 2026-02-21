<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\HR\MarketerPerformanceService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketerPerformanceController extends Controller
{
    protected MarketerPerformanceService $performanceService;

    public function __construct(MarketerPerformanceService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    /**
     * Get marketer performance table.
     * GET /hr/marketers/performance
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'year' => (int) $request->input('year', now()->year),
                'month' => (int) $request->input('month', now()->month),
                'team_id' => $request->input('team_id'),
                'search' => $request->input('search'),
                'per_page' => ApiResponse::getPerPage($request, 15, 100),
            ];

            $marketers = $this->performanceService->getPerformanceTable($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب أداء المسوقين بنجاح',
                'data' => $marketers->items(),
                'meta' => [
                    'total' => $marketers->total(),
                    'per_page' => $marketers->perPage(),
                    'current_page' => $marketers->currentPage(),
                    'last_page' => $marketers->lastPage(),
                    'period' => [
                        'year' => $filters['year'],
                        'month' => $filters['month'],
                    ],
                    'labels_ar' => [
                        'name' => 'اسم الموظف',
                        'target_achievement_rate' => 'نسبة تحقيق الأهداف',
                        'deposits_count' => 'عدد العرابين',
                        'warnings_count' => 'عدد التحذيرات',
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single marketer performance details.
     * GET /hr/marketers/{id}/performance
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $year = (int) $request->input('year', now()->year);
            $month = (int) $request->input('month', now()->month);

            $performance = $this->performanceService->getMarketerPerformance($id, $year, $month);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب تفاصيل أداء المسوق بنجاح',
                'data' => $performance,
                'meta' => [
                    'labels_ar' => [
                        'target_achievement_rate' => 'نسبة تحقيق الأهداف',
                        'deposits_count' => 'عدد العرابين',
                        'warnings_count' => 'عدد التحذيرات',
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
}

