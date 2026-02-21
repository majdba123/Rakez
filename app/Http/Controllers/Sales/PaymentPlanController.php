<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePaymentPlanRequest;
use App\Http\Requests\Sales\UpdatePaymentInstallmentRequest;
use App\Services\Sales\PaymentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class PaymentPlanController extends Controller
{
    protected PaymentPlanService $paymentPlanService;

    public function __construct(PaymentPlanService $paymentPlanService)
    {
        $this->paymentPlanService = $paymentPlanService;
    }

    /**
     * Get payment plan for a reservation.
     * GET /sales/reservations/{id}/payment-plan
     */
    public function show(Request $request, int $reservationId): JsonResponse
    {
        // Check permission (sales or credit)
        if (!$request->user()->can('sales.payment-plan.manage') && !$request->user()->can('credit.payment_plan.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $installments = $this->paymentPlanService->getPaymentPlan($reservationId);
            $summary = $this->paymentPlanService->getPaymentPlanSummary($reservationId);

            return response()->json([
                'success' => true,
                'data' => [
                    'installments' => $installments,
                    'summary' => $summary,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a payment plan for a reservation.
     * POST /sales/reservations/{id}/payment-plan
     */
    public function store(StorePaymentPlanRequest $request, int $reservationId): JsonResponse
    {
        try {
            $installments = $this->paymentPlanService->createPlan(
                $reservationId,
                $request->input('installments'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء خطة الدفعات بنجاح',
                'data' => $installments,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a payment installment.
     * PUT /sales/payment-installments/{id}
     */
    public function update(UpdatePaymentInstallmentRequest $request, int $installmentId): JsonResponse
    {
        try {
            $installment = $this->paymentPlanService->updateInstallment(
                $installmentId,
                $request->validated(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الدفعة بنجاح',
                'data' => $installment,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a payment installment.
     * DELETE /sales/payment-installments/{id}
     */
    public function destroy(Request $request, int $installmentId): JsonResponse
    {
        // Check permission (sales or credit)
        if (!$request->user()->can('sales.payment-plan.manage') && !$request->user()->can('credit.payment_plan.manage')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->paymentPlanService->deleteInstallment($installmentId, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الدفعة بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

