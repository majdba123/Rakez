<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class AccountingNotificationController extends Controller
{
    protected AccountingNotificationService $notificationService;

    public function __construct(AccountingNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get accounting notifications.
     * GET /api/accounting/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'status' => 'nullable|in:pending,read',
                'type' => 'nullable|in:unit_reserved,deposit_received,unit_vacated,reservation_cancelled,commission_confirmed,commission_received',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $filters = $request->only(['from_date', 'to_date', 'status', 'type', 'per_page']);
            $notifications = $this->notificationService->getAccountingNotifications(
                $request->user()->id,
                $filters
            );

            return response()->json([
                'success' => true,
                'message' => 'تم جلب الإشعارات بنجاح',
                'data' => $notifications->items(),
                'meta' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
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
     * Mark notification as read.
     * POST /api/accounting/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $this->notificationService->markAsRead($id);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة الإشعار بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all notifications as read.
     * POST /api/accounting/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $this->notificationService->markAllAsRead($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث جميع الإشعارات بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
