<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\ManagerTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * Controller for manager task API. Only managers can access.
 * Lists and shows tasks of manager's employees (same team).
 */
class ManagerTaskController extends Controller
{
    public function __construct(
        protected ManagerTaskService $managerTaskService
    ) {
    }

    private function ensureManager(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح - يرجى تسجيل الدخول'], 401);
        }
        if (!$user->isManager() && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح - هذه الصلاحية للمديرين أو الأدمن فقط.'], 403);
        }
        return null;
    }

    /**
     * List tasks of manager's employees.
     * GET /manager/tasks
     */
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $manager = $request->user();
            $filters = [
                'status' => $request->input('status'),
                'assigned_to' => $request->input('assigned_to'),
                'section' => $request->input('section'),
                'sort_by' => $request->input('sort_by', 'due_at'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ];
            $perPage = min((int) $request->input('per_page', 15), 100);

            $tasks = $this->managerTaskService->listTasks($manager, $filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المهام بنجاح',
                'data' => $tasks->items(),
                'meta' => [
                    'total' => $tasks->total(),
                    'per_page' => $tasks->perPage(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
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
     * Show a single task.
     * GET /manager/tasks/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $manager = $request->user();
            $task = $this->managerTaskService->showTask($manager, $id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب المهمة بنجاح',
                'data' => $task,
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'غير موجودة') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get task statistics for manager's employees.
     * GET /manager/tasks/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $manager = $request->user();
            $stats = $this->managerTaskService->getStatistics($manager);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب إحصائيات المهام بنجاح',
                'data' => $stats,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
