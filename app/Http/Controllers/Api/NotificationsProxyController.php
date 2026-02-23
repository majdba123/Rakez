<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Credit\CreditNotificationController;
use App\Http\Controllers\Accounting\AccountingNotificationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxy for GET /api/notifications so frontend can call one URL.
 * Accounting role → accounting notifications; all other roles → user notifications (same as credit).
 */
class NotificationsProxyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->index($request);
        }
        // Credit, admin, sales, sales_leader, and any other role: use user notifications (UserNotification for current user)
        return app(CreditNotificationController::class)->index($request);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->markAsRead($request, (int) $id);
        }
        return app(CreditNotificationController::class)->markAsRead($request, (int) $id);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->markAllAsRead($request);
        }
        return app(CreditNotificationController::class)->markAllAsRead($request);
    }
}
