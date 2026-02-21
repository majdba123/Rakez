<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Marketing\MarketingDashboardService;
use Illuminate\Http\JsonResponse;

class MarketingDashboardController extends Controller
{
    public function __construct(
        private MarketingDashboardService $dashboardService
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->dashboardService->getDashboardKPIs());
    }
}
