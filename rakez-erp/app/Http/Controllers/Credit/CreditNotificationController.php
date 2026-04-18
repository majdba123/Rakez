<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Credit\CreditNotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tab 2: Credit Notifications
 */
class CreditNotificationController extends Controller
{
    public function __construct(
        protected CreditNotificationService $notifications,
    ) {}

    /**
     * Get credit user's notifications.
     * GET /credit/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'status' => 'nullable|in:pending,read',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $perPage = min((int) $request->input('per_page', 15), 100);
            $notifications = $this->notifications->listForUser(
                $user,
                $request->only(['from_date', 'to_date', 'status']),
                $perPage,
            );

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully.',
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
     * POST /credit/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $this->notifications->markAsReadForUser($user, $id);

            return response()->json([
                'success' => true,
                'message' => 'Notification status updated successfully.',
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Mark all notifications as read.
     * POST /credit/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $this->notifications->markAllAsReadForUser($user);

            return response()->json([
                'success' => true,
                'message' => 'All notifications updated successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
