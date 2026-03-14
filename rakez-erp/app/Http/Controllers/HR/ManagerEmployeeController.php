<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\ManagerEmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

/**
 * Controller for manager API. Only users with is_manager=true can access.
 * Type is a filter only (query param).
 */
class ManagerEmployeeController extends Controller
{
    public function __construct(
        protected ManagerEmployeeService $managerEmployeeService
    ) {
    }

    /**
     * Ensure user is authenticated and is a manager.
     */
    private function ensureManager(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'غير مصرح - يرجى تسجيل الدخول'], 401);
        }
        if (!$user->isManager()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح - هذه الصلاحية للمديرين فقط.'], 403);
        }
        return null;
    }

    /**
     * List employees. Type is a filter only.
     * GET /manager/employees
     */
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $user = $request->user();
            $filters = [
                'is_active' => $request->input('is_active'),
                'type' => $request->input('type'),
                'team_id' => $request->input('team_id'),
                'department' => $request->input('department'),
                'search' => $request->input('search'),
                'sort_by' => $request->input('sort_by', 'created_at'),
                'sort_order' => $request->input('sort_order', 'desc'),
            ];

            $perPage = min((int) $request->input('per_page', 15), 100);

            $users = $this->managerEmployeeService->listEmployees($user, $filters, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة الموظفين بنجاح',
                'data' => $users->items(),
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
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
     * Show a single employee.
     * GET /manager/employees/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $currentUser = $request->user();
            $user = $this->managerEmployeeService->showEmployee($currentUser, $id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الموظف بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'birthday' => $user->birthday,
                    'phone' => $user->phone,
                    'identity_number' => $user->identity_number,
                    'nationality' => $user->nationality,
                    'type' => $user->type,
                    'job_title' => $user->job_title,
                    'department' => $user->department,
                    'salary' => $user->salary,
                    'additional_benefits' => $user->additional_benefits,
                    'probation_period_days' => $user->probation_period_days,
                    'date_of_works' => $user->date_of_works,
                    'work_type' => $user->work_type,
                    'is_manager' => $user->is_manager,
                    'is_active' => $user->is_active,
                    'email' => $user->email,
                    'iban' => $user->iban,
                    'cv_path' => $user->cv_path,
                    'contract_path' => $user->contract_path,
                    'signature_path' => $user->signature_path,
                    'work_phone_approval' => $user->work_phone_approval,
                    'logo_usage_approval' => $user->logo_usage_approval,
                    'team' => $user->team ? [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                    ] : null,
                    'contract_end_date' => $user->contract_end_date,
                    'employee_contracts' => $user->employeeContracts,
                    'is_in_probation' => $user->isInProbation(),
                    'probation_end_date' => $user->getProbationEndDate(),
                    'warnings_count' => $user->warnings->count(),
                    'warnings' => $user->warnings,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = 500;
            if (str_contains($e->getMessage(), 'not found')) {
                $statusCode = 404;
            }
            if (str_contains($e->getMessage(), 'غير مصرح')) {
                $statusCode = 403;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Add a review for an employee. Only managers can add reviews.
     * POST /manager/employees/{id}/reviews
     * Body: { "comment": "..." }
     */
    public function storeReview(Request $request, int $id): JsonResponse
    {
        try {
            $currentUser = $request->user();
            if (!$currentUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح - يرجى تسجيل الدخول',
                ], 401);
            }

            $request->validate([
                'comment' => 'required|string|max:2000',
            ], [
                'comment.required' => 'التعليق مطلوب',
            ]);

            $review = $this->managerEmployeeService->addReview(
                $currentUser,
                $id,
                $request->input('comment')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة التقييم بنجاح',
                'data' => [
                    'id' => $review->id,
                    'employee_id' => $review->employee_id,
                    'manager_id' => $review->manager_id,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->toIso8601String(),
                ],
            ], 201);
        } catch (Exception $e) {
            $statusCode = 500;
            if (str_contains($e->getMessage(), 'غير موجود')) {
                $statusCode = 404;
            }
            if (str_contains($e->getMessage(), 'غير مصرح')) {
                $statusCode = 403;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get all reviews for an employee.
     * GET /manager/employees/{employeeId}/reviews
     */
    public function indexReviews(Request $request, int $employeeId): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $currentUser = $request->user();
            $perPage = min((int) $request->input('per_page', 15), 100);
            $reviews = $this->managerEmployeeService->getReviewsForEmployee($currentUser, $employeeId, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب التقييمات بنجاح',
                'data' => collect($reviews->items())->map(fn ($r) => $this->formatReview($r)),
                'meta' => [
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->exceptionStatusCode($e));
        }
    }

    /**
     * Show a single review by id.
     * GET /manager/employees/{employeeId}/reviews/{reviewId}
     */
    public function showReview(Request $request, int $employeeId, int $reviewId): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $currentUser = $request->user();
            $review = $this->managerEmployeeService->showReview($currentUser, $employeeId, $reviewId);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب التقييم بنجاح',
                'data' => $this->formatReview($review),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->exceptionStatusCode($e));
        }
    }

    /**
     * Update a review. Only the manager who created it can update.
     * PUT /manager/employees/{employeeId}/reviews/{reviewId}
     * Body: { "comment": "..." }
     */
    public function updateReview(Request $request, int $employeeId, int $reviewId): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $currentUser = $request->user();
            $request->validate([
                'comment' => 'required|string|max:2000',
            ], ['comment.required' => 'التعليق مطلوب']);

            $review = $this->managerEmployeeService->updateReview(
                $currentUser,
                $employeeId,
                $reviewId,
                $request->input('comment')
            );

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث التقييم بنجاح',
                'data' => $this->formatReview($review),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->exceptionStatusCode($e));
        }
    }

    /**
     * Delete a review. Only the manager who created it can delete.
     * DELETE /manager/employees/{employeeId}/reviews/{reviewId}
     */
    public function deleteReview(Request $request, int $employeeId, int $reviewId): JsonResponse
    {
        if ($err = $this->ensureManager($request)) {
            return $err;
        }
        try {
            $currentUser = $request->user();
            $this->managerEmployeeService->deleteReview($currentUser, $employeeId, $reviewId);

            return response()->json([
                'success' => true,
                'message' => 'تم حذف التقييم بنجاح',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->exceptionStatusCode($e));
        }
    }

    private function formatReview($review): array
    {
        return [
            'id' => $review->id,
            'employee_id' => $review->employee_id,
            'manager_id' => $review->manager_id,
            'comment' => $review->comment,
            'manager' => $review->manager ? ['id' => $review->manager->id, 'name' => $review->manager->name] : null,
            'employee' => $review->employee ? ['id' => $review->employee->id, 'name' => $review->employee->name] : null,
            'created_at' => $review->created_at?->toIso8601String(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    private function exceptionStatusCode(Exception $e): int
    {
        if (str_contains($e->getMessage(), 'غير موجود')) {
            return 404;
        }
        if (str_contains($e->getMessage(), 'غير مصرح')) {
            return 403;
        }
        return 500;
    }
}
