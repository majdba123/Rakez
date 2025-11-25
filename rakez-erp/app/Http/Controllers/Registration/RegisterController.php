<?php

namespace App\Http\Controllers\Registration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\RegisterUser;
use App\Http\Requests\registartion\UpdateUser;
use App\Services\registartion\register;
use Illuminate\Http\JsonResponse;
use App\Models\User;

class RegisterController extends Controller
{
    protected $userService;

    public function __construct(register $userService)
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

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * List all employees with filtering and pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list_employees(Request $request): JsonResponse
    {
        $users = $this->userService->listEmployees($request->all());

        return response()->json([
            'message' => 'Employees retrieved successfully',
            'data' => $users,
        ]);
    }

    /**
     * Show specific employee details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show_employee($id): JsonResponse
    {
        $user = $this->userService->showEmployee($id);

        return response()->json([
            'message' => 'Employee retrieved successfully',
            'user' => $user,
        ]);
    }

    /**
     * Update employee information
     *
     * @param UpdateUser $request
     * @param int $id
     * @return JsonResponse
     */
    public function update_employee(UpdateUser $request, $id): JsonResponse
    {
        $validatedData = $request->validated();
        $user = $this->userService->updateEmployee($id, $validatedData);

        return response()->json([
            'message' => 'Employee updated successfully',
            'user' => $user,
        ]);
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

        return response()->json([
            'message' => 'Employee deleted successfully',
        ]);
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

        return response()->json([
            'message' => 'Employee restored successfully',
        ]);
    }

    /**
     * Get employee statistics
     *
     * @return JsonResponse
     */
    public function employee_stats(): JsonResponse
    {
        $stats = $this->userService->getEmployeeStats();

        return response()->json([
            'message' => 'Employee statistics retrieved successfully',
            'stats' => $stats,
        ]);
    }
}
