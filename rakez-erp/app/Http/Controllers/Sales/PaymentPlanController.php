<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StorePaymentPlanRequest;
use App\Http\Requests\Sales\UpdatePaymentInstallmentRequest;
use App\Models\User;
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

    private function userCanManagePaymentPlans(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can('sales.payment-plan.manage') || $user->can('credit.payment_plan.manage');
    }

    /**
     * Get payment plan for a reservation.
     * GET /sales/reservations/{id}/payment-plan
     */
    public function show(Request $request, int $reservationId): JsonResponse
    {
        if (! $this->userCanManagePaymentPlans($request->user())) {
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
        if (! $this->userCanManagePaymentPlans($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
    public function update(UpdatePaymentInstallmentRequest $request, int $id): JsonResponse
    {
        if (! $this->userCanManagePaymentPlans($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $installment = $this->paymentPlanService->updateInstallment(
                $id,
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
    public function destroy(Request $request, int $id): JsonResponse
    {
        if (! $this->userCanManagePaymentPlans($request->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->paymentPlanService->deleteInstallment($id, $request->user());

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

