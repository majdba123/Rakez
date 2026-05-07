<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Credit\CreditNotificationController;
use App\Http\Controllers\Accounting\AccountingNotificationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxy for GET /api/notifications so frontend can call one URL.
 * Dispatches to credit or accounting notifications based on user role.
 */
class NotificationsProxyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('credit') || $request->user()->hasRole('admin')) {
            return app(CreditNotificationController::class)->index($request);
        }
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->index($request);
        }
        return response()->json([
            'success' => false,
            'message' => 'Use /api/credit/notifications or /api/accounting/notifications',
        ], 404);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        if ($request->user()->hasRole('credit') || $request->user()->hasRole('admin')) {
            return app(CreditNotificationController::class)->markAsRead($request, (int) $id);
        }
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->markAsRead($request, (int) $id);
        }
        return response()->json(['success' => false, 'message' => 'Not found'], 404);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('credit') || $request->user()->hasRole('admin')) {
            return app(CreditNotificationController::class)->markAllAsRead($request);
        }
        if ($request->user()->hasRole('accounting')) {
            return app(AccountingNotificationController::class)->markAllAsRead($request);
        }
        return response()->json(['success' => false, 'message' => 'Not found'], 404);
    }
}
