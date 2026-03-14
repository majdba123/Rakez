<?php

namespace App\Services\HR;

use App\Models\ManagerEmployeeReview;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Exception;

/**
 * Service for listing employees with manager-based filtering.
 * When the requesting user is a manager (is_manager=true), only employees
 * with the same type as the manager are returned.
 */
class ManagerEmployeeService
{
    /**
     * List employees. If the requesting user is a manager, filter by same type.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listEmployees(User $requestingUser, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = User::with(['team', 'warnings']);

            // Manager filter: only employees with same type as the manager
            if ($requestingUser->isManager() && $requestingUser->type !== null) {
                $query->where('type', $requestingUser->type);
            }

            // Apply request filters
            if (isset($filters['is_active']) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            }

            if (isset($filters['type']) && $filters['type'] !== null && $filters['type'] !== '') {
                $query->where('type', $filters['type']);
            }

            if (isset($filters['team_id']) && $filters['team_id'] !== null && $filters['team_id'] !== '') {
                $query->where('team_id', $filters['team_id']);
            }

            if (isset($filters['department']) && $filters['department'] !== null && $filters['department'] !== '') {
                $query->where('department', $filters['department']);
            }

            if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
                $search = trim($filters['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Sorting
            $allowedSortFields = ['id', 'name', 'email', 'type', 'created_at', 'updated_at'];
            $sortBy = $filters['sort_by'] ?? 'created_at';
            $sortOrder = strtolower($filters['sort_order'] ?? 'desc');

            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            if (!in_array($sortOrder, ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortBy, $sortOrder);

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to list employees: ' . $e->getMessage());
        }
    }

    /**
     * Get a single employee. Managers can only view employees of their type.
     *
     * @throws Exception When employee not found or manager cannot access (different type)
     */
    public function showEmployee(User $requestingUser, int $id): User
    {
        $user = User::with([
            'team',
            'warnings',
            'employeeContracts',
            'salesTargetsAsMarketer',
        ])->find($id);

        if (!$user) {
            throw new Exception('Employee not found.');
        }

        // Manager can only view employees of same type
        if ($requestingUser->isManager() && $requestingUser->type !== null) {
            if ($user->type !== $requestingUser->type) {
                throw new Exception('غير مصرح - لا يمكنك عرض موظف من نوع آخر.');
            }
        }

        return $user;
    }

    /**
     * Add a review for an employee. Only managers can add reviews, and only for employees of their type.
     *
     * @throws Exception When not a manager, employee not found, or employee is different type
     */
    public function addReview(User $manager, int $employeeId, string $comment): ManagerEmployeeReview
    {
        if (!$manager->isManager()) {
            throw new Exception('غير مصرح - فقط المديرون يمكنهم إضافة تقييمات.');
        }

        $employee = User::find($employeeId);
        if (!$employee) {
            throw new Exception('الموظف غير موجود.');
        }

        if ($manager->type !== null && $employee->type !== $manager->type) {
            throw new Exception('غير مصرح - لا يمكنك إضافة تقييم لموظف من نوع آخر.');
        }

        return ManagerEmployeeReview::create([
            'employee_id' => $employeeId,
            'manager_id' => $manager->id,
            'comment' => $comment,
        ]);
    }

    /**
     * Get all reviews for an employee. Managers can only view if employee is same type.
     *
     * @return LengthAwarePaginator<ManagerEmployeeReview>
     */
    public function getReviewsForEmployee(User $requestingUser, int $employeeId, int $perPage = 15): LengthAwarePaginator
    {
        $this->ensureCanAccessEmployee($requestingUser, $employeeId);

        return ManagerEmployeeReview::with(['manager', 'employee'])
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a single review by id. Manager can view if employee is same type.
     */
    public function showReview(User $requestingUser, int $employeeId, int $reviewId): ManagerEmployeeReview
    {
        $this->ensureCanAccessEmployee($requestingUser, $employeeId);

        $review = ManagerEmployeeReview::with(['manager', 'employee'])
            ->where('employee_id', $employeeId)
            ->where('id', $reviewId)
            ->first();

        if (!$review) {
            throw new Exception('التقييم غير موجود.');
        }

        return $review;
    }

    /**
     * Update a review. Only the manager who created it can update.
     */
    public function updateReview(User $manager, int $employeeId, int $reviewId, string $comment): ManagerEmployeeReview
    {
        $this->ensureCanAccessEmployee($manager, $employeeId);

        $review = ManagerEmployeeReview::where('employee_id', $employeeId)->where('id', $reviewId)->first();

        if (!$review) {
            throw new Exception('التقييم غير موجود.');
        }

        if ($review->manager_id !== $manager->id) {
            throw new Exception('غير مصرح - لا يمكنك تعديل تقييم لم تضفه أنت.');
        }

        $review->update(['comment' => $comment]);

        return $review->fresh(['manager', 'employee']);
    }

    /**
     * Delete a review. Only the manager who created it can delete.
     */
    public function deleteReview(User $manager, int $employeeId, int $reviewId): void
    {
        $this->ensureCanAccessEmployee($manager, $employeeId);

        $review = ManagerEmployeeReview::where('employee_id', $employeeId)->where('id', $reviewId)->first();

        if (!$review) {
            throw new Exception('التقييم غير موجود.');
        }

        if ($review->manager_id !== $manager->id) {
            throw new Exception('غير مصرح - لا يمكنك حذف تقييم لم تضفه أنت.');
        }

        $review->delete();
    }

    /**
     * Ensure the requesting user can access the employee (for viewing reviews).
     */
    private function ensureCanAccessEmployee(User $requestingUser, int $employeeId): void
    {
        $employee = User::find($employeeId);
        if (!$employee) {
            throw new Exception('الموظف غير موجود.');
        }

        if ($requestingUser->isManager() && $requestingUser->type !== null) {
            if ($employee->type !== $requestingUser->type) {
                throw new Exception('غير مصرح - لا يمكنك الوصول لتقييمات موظف من نوع آخر.');
            }
        }
    }
}
