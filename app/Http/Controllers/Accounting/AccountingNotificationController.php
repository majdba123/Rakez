<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Accounting\AccountingNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

            $filters = $request->only(['from_date', 'to_date', 'status', 'type']);
            $filters['per_page'] = ApiResponse::getPerPage($request, 15, 100);
            $notifications = $this->notificationService->getAccountingNotifications(
                $request->user()->id,
                $filters
            );

            return ApiResponse::success(
                $notifications->items(),
                'تم جلب الإشعارات بنجاح',
                200,
                [
                    'pagination' => [
                        'total' => $notifications->total(),
                        'count' => $notifications->count(),
                        'per_page' => $notifications->perPage(),
                        'current_page' => $notifications->currentPage(),
                        'total_pages' => $notifications->lastPage(),
                        'has_more_pages' => $notifications->hasMorePages(),
                    ],
                ]
            );
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
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

            return ApiResponse::success(null, 'تم تحديث حالة الإشعار بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
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

            return ApiResponse::success(null, 'تم تحديث جميع الإشعارات بنجاح');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }
}
