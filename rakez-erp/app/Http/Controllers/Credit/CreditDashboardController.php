<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Services\Credit\CreditDashboardService;
use Illuminate\Http\JsonResponse;
use Exception;

class CreditDashboardController extends Controller
{
    protected CreditDashboardService $dashboardService;

    public function __construct(CreditDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get Credit dashboard KPIs.
     * GET /credit/dashboard
     */
    public function index(): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getKpis();
            $stageBreakdown = $this->dashboardService->getStageBreakdown();
            $stageLabelsAr = $this->dashboardService->getStageLabelsAr();
            $titleTransferBreakdown = $this->dashboardService->getTitleTransferBreakdown();
            $kpisLabelsAr = [
                'confirmed_bookings_count' => 'الحجوزات المؤكدة',
                'negotiation_bookings_count' => 'التفاوض',
                'waiting_bookings_count' => 'الانتظار',
                'requires_review_count' => 'يحتاج مراجعة',
                'rejected_with_paid_down_payment_count' => 'المشاريع التي تم دفع عربون لها وتم رفضها من البنك',
                'projects_in_progress_count' => 'المشاريع قيد التنفيذ',
                'rejected_by_bank_count' => 'المشاريع المرفوضة من البنك',
                'overdue_stages' => 'مراحل متأخرة',
                'pending_accounting_confirmation' => 'في انتظار تأكيد المحاسبة',
                'in_title_transfer_count' => 'قيد نقل الملكية',
                'sold_projects_count' => 'المشاريع المباعة',
            ];
            $titleTransferLabelsAr = [
                'preparation_count' => 'فترة التجهيز قبل الإفراغ',
                'scheduled_count' => 'تنفيذ العقود',
            ];

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات لوحة تحكم الائتمان بنجاح',
                'data' => [
                    'kpis' => $kpis,
                    'kpis_labels_ar' => $kpisLabelsAr,
                    'stage_breakdown' => $stageBreakdown,
                    'stage_labels_ar' => $stageLabelsAr,
                    'title_transfer_breakdown' => $titleTransferBreakdown,
                    'title_transfer_labels_ar' => $titleTransferLabelsAr,
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
     * Refresh dashboard cache.
     * POST /credit/dashboard/refresh
     */
    public function refresh(): JsonResponse
    {
        try {
            $this->dashboardService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}



