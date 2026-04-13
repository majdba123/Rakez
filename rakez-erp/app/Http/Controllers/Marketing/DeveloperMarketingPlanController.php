<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\Marketing\DeveloperMarketingPlanService;
use App\Services\Marketing\MarketingProjectService;
use App\Services\Pdf\PdfFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

class DeveloperMarketingPlanController extends Controller
{
    public function __construct(
        private DeveloperMarketingPlanService $planService,
        private MarketingProjectService $projectService
    ) {}

    public function show(int $contractId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->planService->getPlanForDeveloper($contractId)
        ]);
    }

    /**
     * Developer marketing plan as PDF (same template as marketing report export).
     * GET /api/marketing/developer-plans/{contractId}/pdf
     */
    public function downloadPdf(int $contractId): Response|JsonResponse
    {
        try {
            $planData = $this->planService->getPlanForDeveloper($contractId);
            if (empty($planData['plan'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على خطة تسويق المطور لهذا العقد',
                ], 404);
            }

            $contract = Contract::find($contractId);
            $projectName = $contract?->project_name ?? '';

            return PdfFactory::download(
                'marketing.developer_plan_export',
                [
                    'contractId' => $contractId,
                    'projectName' => $projectName,
                    'plan' => $planData,
                ],
                "developer_marketing_plan_contract_{$contractId}.pdf"
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'العقد غير موجود',
            ], 404);
        } catch (MpdfException $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذّر توليد الـ PDF (خط أو مكتبة mPDF). راجع storage/fonts وملفات DejaVu.',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 503);
        }
    }

    /**
     * Developer plan PDF data (unified report shape). JSON only; frontend uses buildDocumentPdf(payload).
     * GET /api/marketing/reports/developer-plan/{contractId}/pdf-data
     */
    public function pdfData(int $contractId): JsonResponse
    {
        try {
            $planData = $this->planService->getPlanForDeveloper($contractId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Contract or plan not found'], 404);
        }
        $contract = $planData['contract'] ?? [];
        $plan = $planData['plan'] ?? null;
        $basis = $contract['pricing_basis'] ?? [];

        $sections = [];
        $sections[] = [
            'sectionTitle' => 'بيانات العقد',
            'infoRows' => [
                ['نسبة السعي %', (string) ($contract['commission_percent'] ?? '')],
                ['أساس التسعير (المصدر)', (string) ($basis['source'] ?? '')],
                ['إجمالي سعر الوحدات (أساس العمولة)', (string) ($basis['total_unit_price'] ?? '')],
                ['وحدات متاحة / إجمالي', ($basis['available_units_count'] ?? '') . ' / ' . ($basis['all_units_count'] ?? '')],
                ['متوسط سعر الوحدة (متاح)', (string) ($basis['average_unit_price'] ?? '')],
                ['avg_property_value (مخزن)', (string) ($basis['avg_property_value_stored'] ?? '')],
            ],
        ];
        if ($plan) {
            $mv = is_array($plan) ? ($plan['marketing_value'] ?? null) : ($plan->marketing_value ?? null);
            $sections[] = [
                'sectionTitle' => 'خطة التسويق',
                'infoRows' => [
                    ['ميزانية التسويق', (string) ($planData['total_budget_display'] ?? $planData['total_budget'] ?? $mv ?? '')],
                    ['مدة التسويق', $planData['marketing_duration_ar'] ?? ''],
                    ['الظهور المتوقع', $planData['expected_impressions_display_ar'] ?? ''],
                    ['النقرات المتوقعة', $planData['expected_clicks_display_ar'] ?? ''],
                ],
            ];
        }

        $payload = [
            'title' => 'خطة تسويق المطور',
            'subtitle' => '',
            'sections' => $sections,
            'footer' => '',
        ];
        return response()->json($payload, 200, ['Content-Type' => 'application/json']);
    }

    /**
     * حساب ميزانية الحملة: عمولة = نسبة السعي في العقد × متوسط سعر الوحدات، ميزانية الحملة = عمولة × نسبة التسويق (6%-10%).
     */
    public function calculateBudget(Request $request): JsonResponse
    {
        $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'marketing_percent' => 'required|numeric|min:6|max:10',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $data = $this->projectService->calculateCampaignBudget(
            (int) $request->input('contract_id'),
            $request->only(['marketing_percent', 'unit_price'])
        );

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(\App\Http\Requests\Marketing\StoreDeveloperPlanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $plan = $this->planService->createOrUpdatePlan(
            $validated['contract_id'],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ خطة تسويق المطور بنجاح',
            'data' => $plan
        ]);
    }
}
