<?php

namespace App\Http\Controllers\Registration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterUser;
use App\Http\Requests\Registration\UpdateUser;
use App\Http\Responses\ApiResponse;
use App\Services\Registration\RegisterService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;

class RegisterController extends Controller
{
    protected RegisterService $userService;

    public function __construct(RegisterService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Handle the registration of a new user.
     *
     * @param RegisterUser $request
     * @return JsonResponse
     */
    public function add_employee(RegisterUser $request): JsonResponse
    {
        $validatedData = $request->validated();
        $user = $this->userService->register($validatedData);

        return (new UserResource($user))
            ->additional(['message' => 'User registered successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * List all employees with filtering and pagination
     *
     * @param Request $request
     * @return mixed
     */
    public function list_employees(Request $request): mixed
    {
        // Validate query parameters
        $validated = $request->validate([
            'type' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,deleted',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'sort_by' => 'nullable|string|in:created_at,name,email',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = $this->userService->listEmployees($validated);

        return UserResource::collection($users)
            ->additional(['message' => 'Employees retrieved successfully']);
    }

    /**
     * Show specific employee details
     *
     * @param int $id
     * @return mixed
     */
    public function show_employee($id): mixed
    {
        $user = $this->userService->showEmployee($id);

        return (new UserResource($user))
            ->additional(['message' => 'Employee retrieved successfully']);
    }

    /**
     * Update employee information
     *
     * @param UpdateUser $request
     * @param int $id
     * @return mixed
     */
    public function update_employee(UpdateUser $request, $id): mixed
    {
        $validatedData = $request->validated();
        $user = $this->userService->updateEmployee($id, $validatedData);

        return (new UserResource($user))
            ->additional(['message' => 'Employee updated successfully']);
    }

    /**
     * Delete employee
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete_employee($id): JsonResponse
    {
        $this->userService->deleteEmployee($id);

        return ApiResponse::success(null, 'Employee deleted successfully');
    }

    /**
     * Restore soft deleted employee
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore_employee($id): JsonResponse
    {
        $this->userService->restoreEmployee($id);

        return ApiResponse::success(null, 'Employee restored successfully');
    }

    /**
     * Get employee statistics
     *
     * @return JsonResponse
     */
    public function employee_stats(): JsonResponse
    {
        $stats = $this->userService->getEmployeeStats();

        return ApiResponse::success($stats, 'Employee statistics retrieved successfully');
    }

    /**
     * List all available roles for dropdown
     *
     * @return JsonResponse
     */
    public function list_roles(): JsonResponse
    {
        $roles = \Spatie\Permission\Models\Role::all(['id', 'name']);

        return ApiResponse::success($roles, 'Roles retrieved successfully');
    }
}
