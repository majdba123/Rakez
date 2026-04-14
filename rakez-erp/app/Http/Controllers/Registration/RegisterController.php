<?php

namespace App\Http\Controllers\Registration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\RegisterUser;
use App\Http\Requests\registartion\UpdateUser;
use App\Http\Requests\registartion\ImportEmployeesCsv;
use App\Services\registartion\register;
use App\Jobs\ProcessEmployeesCsv;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Http\Resources\UserResource;

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

        return (new UserResource($user))
            ->additional(['message' => 'User registered successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Upload a CSV file for bulk employee import.
     * Validates the file and header, then dispatches a queue job.
     * Returns an import ID the frontend can poll for status.
     */
    public function import_employees_csv(ImportEmployeesCsv $request): JsonResponse
    {
        $file = $request->file('file');

        // Quick header validation before storing
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Unable to read the CSV file.'], 422);
        }

        $header = fgetcsv($handle);
        fclose($handle);

        if (!$header) {
            return response()->json(['message' => 'CSV file is empty or has no header row.'], 422);
        }

        $header = array_map(fn ($col) => strtolower(trim($col)), $header);
        $requiredColumns = ['name', 'email', 'password', 'type'];
        $missing = array_diff($requiredColumns, $header);

        if (!empty($missing)) {
            return response()->json([
                'message' => 'CSV is missing required columns.',
                'missing_columns' => array_values($missing),
            ], 422);
        }

        $storedPath = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'type' => CsvImport::TYPE_EMPLOYEES,
            'uploaded_by' => Auth::id(),
            'file_path' => $storedPath,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        ProcessEmployeesCsv::dispatch($csvImport->id);

        return response()->json([
            'message' => 'CSV uploaded successfully. Import is being processed.',
            'import_id' => $csvImport->id,
            'status' => $csvImport->status,
        ], 202);
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
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|max:255',
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

    /**
     * List all available roles for dropdown
     *
     * @return JsonResponse
     */
    public function list_roles(): JsonResponse
    {
        $roles = \Spatie\Permission\Models\Role::all(['id', 'name']);

        return response()->json([
            'message' => 'Roles retrieved successfully',
            'data' => $roles,
        ]);
    }
}
