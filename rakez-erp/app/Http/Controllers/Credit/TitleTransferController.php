<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Services\Credit\TitleTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class TitleTransferController extends Controller
{
    protected TitleTransferService $transferService;

    public function __construct(TitleTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Initialize title transfer for a reservation.
     * POST /credit/bookings/{id}/title-transfer
     */
    public function initialize(Request $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->initializeTransfer($id, $request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم بدء عملية نقل الملكية بنجاح',
                'data' => $transfer,
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Schedule title transfer date.
     * PATCH /credit/title-transfer/{id}/schedule
     */
    public function schedule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $transfer = $this->transferService->scheduleTransfer(
                $id,
                $validated['scheduled_date'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'تم جدولة موعد نقل الملكية بنجاح',
                'data' => $transfer,
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
     * Cancel scheduled evacuation date (إلغاء موعد الافراغ).
     * PATCH /credit/title-transfer/{id}/unschedule
     */
    public function unschedule(Request $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->unscheduleTransfer($id);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء موعد الإفراغ بنجاح',
                'data' => $transfer,
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
     * Complete title transfer.
     * POST /credit/title-transfer/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->completeTransfer($id, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'تم إكمال نقل الملكية بنجاح',
                'data' => $transfer,
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
     * Get sold projects (completed title transfers).
     * GET /credit/sold-projects
     */
    public function soldProjects(Request $request): JsonResponse
    {
        try {
            $filters = [
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'contract_id' => $request->input('contract_id'),
            ];

            $projects = $this->transferService->getSoldProjects($filters);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المشاريع المباعة بنجاح',
                'data' => $projects->map(fn($t) => [
                    'transfer_id' => $t->id,
                    'reservation_id' => $t->sales_reservation_id,
                    'project_name' => $t->reservation?->contract?->project_name,
                    'unit_number' => $t->reservation?->contractUnit?->unit_number,
                    'client_name' => $t->reservation?->client_name,
                    'completed_date' => $t->completed_date,
                    'processed_by' => $t->processedBy?->name,
                ]),
                'meta' => [
                    'total' => $projects->count(),
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
     * Get pending title transfers.
     * GET /credit/title-transfers/pending
     */
    public function pending(): JsonResponse
    {
        try {
            $transfers = $this->transferService->getPendingTransfers();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب طلبات نقل الملكية المعلقة بنجاح',
                'data' => $transfers,
                'meta' => [
                    'total' => $transfers->count(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}



