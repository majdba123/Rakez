<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeWarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class EmployeeWarningController extends Controller
{
    /**
     * List warnings for an employee.
     * GET /hr/users/{id}/warnings
     */
    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $query = $user->warnings()->with('issuer');

            // Filter by year
            if ($request->has('year')) {
                $query->whereYear('warning_date', $request->input('year'));
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            $warnings = $query->orderBy('warning_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'message' => 'تم جلب قائمة التحذيرات بنجاح',
                'data' => $warnings->map(fn($w) => [
                    'id' => $w->id,
                    'type' => $w->type,
                    'reason' => $w->reason,
                    'details' => $w->details,
                    'is_auto_generated' => $w->is_auto_generated,
                    'warning_date' => $w->warning_date,
                    'issued_by' => $w->issuer ? [
                        'id' => $w->issuer->id,
                        'name' => $w->issuer->name,
                    ] : null,
                    'created_at' => $w->created_at,
                ]),
                'meta' => [
                    'total' => $warnings->count(),
                    'employee' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
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
     * Issue a warning to an employee.
     * POST /hr/users/{id}/warnings
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:performance,attendance,behavior,other',
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:2000',
            'warning_date' => 'nullable|date',
        ]);

        try {
            $user = User::findOrFail($id);

            $warning = EmployeeWarning::create([
                'user_id' => $user->id,
                'issued_by' => $request->user()->id,
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
                'is_auto_generated' => false,
                'warning_date' => $validated['warning_date'] ?? now()->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إصدار التحذير بنجاح',
                'data' => [
                    'id' => $warning->id,
                    'type' => $warning->type,
                    'reason' => $warning->reason,
                    'warning_date' => $warning->warning_date,
                    'employee' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                ],
            ], 201);
        } catch (Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'No query results') ? 404 : 500;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Delete a warning.
     * DELETE /hr/warnings/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $warning = EmployeeWarning::findOrFail($id);
            $warning->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف التحذير بنجاح',
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

