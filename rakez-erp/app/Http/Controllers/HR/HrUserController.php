<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\registartion\register as RegisterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Exception;

class HrUserController extends Controller
{
    protected RegisterService $registerService;

    public function __construct(RegisterService $registerService)
    {
        $this->registerService = $registerService;
    }

    /**
     * List all employees.
     * GET /hr/users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['team', 'warnings']);

            // Filters
            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('team_id')) {
                $query->where('team_id', $request->input('team_id'));
            }

            if ($request->has('department')) {
                $query->where('department', $request->input('department'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = min((int) $request->input('per_page', 15), 100);
            $users = $query->paginate($perPage);

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
     * Get full employee profile.
     * GET /hr/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = User::with([
                'team',
                'warnings',
                'employeeContracts',
                'salesTargetsAsMarketer',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'تم جلب بيانات الموظف بنجاح',
                'data' => [
                    // Personal Information
                    'id' => $user->id,
                    'name' => $user->name,
                    'birthday' => $user->birthday,
                    'phone' => $user->phone,
                    'identity_number' => $user->identity_number,
                    'nationality' => $user->nationality,

                    // Job Information
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

                    // Contact & Banking
                    'email' => $user->email,
                    'iban' => $user->iban,

                    // Documents & Approvals
                    'cv_path' => $user->cv_path,
                    'contract_path' => $user->contract_path,
                    'signature_path' => $user->signature_path,
                    'work_phone_approval' => $user->work_phone_approval,
                    'logo_usage_approval' => $user->logo_usage_approval,

                    // Team
                    'team' => $user->team ? [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                    ] : null,

                    // Contracts
                    'contract_end_date' => $user->contract_end_date,
                    'employee_contracts' => $user->employeeContracts,

                    // Probation status
                    'is_in_probation' => $user->isInProbation(),
                    'probation_end_date' => $user->getProbationEndDate(),

                    // Warnings
                    'warnings_count' => $user->warnings->count(),
                    'warnings' => $user->warnings,

                    // Timestamps
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
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
     * Create a new employee.
     * POST /hr/users
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
            'type' => 'required|integer|between:0,7',
            'is_manager' => 'nullable|boolean',
            'team_id' => 'nullable|integer|exists:teams,id',
            'identity_number' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'nationality' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'additional_benefits' => 'nullable|string',
            'probation_period_days' => 'nullable|integer|min:0',
            'date_of_works' => 'nullable|date',
            'work_type' => 'nullable|in:full_time,part_time,contract,remote',
            'iban' => 'nullable|string|max:50',
            'work_phone_approval' => 'nullable|boolean',
            'logo_usage_approval' => 'nullable|boolean',
        ]);

        try {
            $user = $this->registerService->register($validated);

            // Update additional fields not handled by register service
            $additionalFields = [
                'nationality', 'job_title', 'department', 'additional_benefits',
                'probation_period_days', 'work_type', 'work_phone_approval', 'logo_usage_approval'
            ];

            $updateData = [];
            foreach ($additionalFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $updateData[$field] = $validated[$field];
                }
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الموظف بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update employee profile.
     * PUT /hr/users/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'phone' => 'sometimes|string|max:20',
            'team_id' => 'nullable|integer|exists:teams,id',
            'identity_number' => 'nullable|string|max:20',
            'birthday' => 'nullable|date',
            'nationality' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary' => 'nullable|numeric|min:0',
            'additional_benefits' => 'nullable|string',
            'probation_period_days' => 'nullable|integer|min:0',
            'date_of_works' => 'nullable|date',
            'work_type' => 'nullable|in:full_time,part_time,contract,remote',
            'iban' => 'nullable|string|max:50',
            'work_phone_approval' => 'nullable|boolean',
            'logo_usage_approval' => 'nullable|boolean',
            'contract_end_date' => 'nullable|date',
            'is_manager' => 'nullable|boolean',
        ]);

        try {
            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات الموظف بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
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
     * Toggle employee active status.
     * PATCH /hr/users/{id}/status
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        try {
            $user = User::findOrFail($id);
            $user->update(['is_active' => $validated['is_active']]);

            return response()->json([
                'success' => true,
                'message' => $validated['is_active'] 
                    ? 'تم تفعيل حساب الموظف بنجاح'
                    : 'تم تعطيل حساب الموظف بنجاح',
                'data' => [
                    'id' => $user->id,
                    'is_active' => $user->is_active,
                ],
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
     * Delete an employee.
     * DELETE /hr/users/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الموظف بنجاح',
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
     * Upload files for employee (CV, contract, signature).
     * POST /hr/users/{id}/files
     */
    public function uploadFiles(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'contract' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'signature' => 'nullable|file|mimes:png,jpg,jpeg|max:2048',
        ]);

        try {
            $user = User::findOrFail($id);
            $updateData = [];

            if ($request->hasFile('cv')) {
                // Delete old file if exists
                if ($user->cv_path) {
                    Storage::disk('public')->delete($user->cv_path);
                }
                $path = $request->file('cv')->store("employees/{$id}/cv", 'public');
                $updateData['cv_path'] = $path;
            }

            if ($request->hasFile('contract')) {
                if ($user->contract_path) {
                    Storage::disk('public')->delete($user->contract_path);
                }
                $path = $request->file('contract')->store("employees/{$id}/contract", 'public');
                $updateData['contract_path'] = $path;
            }

            if ($request->hasFile('signature')) {
                if ($user->signature_path) {
                    Storage::disk('public')->delete($user->signature_path);
                }
                $path = $request->file('signature')->store("employees/{$id}/signature", 'public');
                $updateData['signature_path'] = $path;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الملفات بنجاح',
                'data' => [
                    'cv_path' => $user->cv_path,
                    'contract_path' => $user->contract_path,
                    'signature_path' => $user->signature_path,
                ],
            ], 200);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}

