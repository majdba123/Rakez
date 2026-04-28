<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingEmployeeController extends Controller
{
    /**
     * List marketing employees.
     * GET /api/marketing/employees
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['team'])
            ->where('type', 'marketing');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('team_id')) {
            $query->where('team_id', $request->input('team_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min((int) $request->input('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب قائمة موظفي التسويق بنجاح',
            'data' => collect($users->items())->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'job_title' => $u->job_title,
                'is_active' => $u->is_active,
                'is_manager' => $u->is_manager,
                'is_executive_director' => (bool) $u->is_executive_director,
                'team' => $u->team ? [
                    'id' => $u->team->id,
                    'name' => $u->team->name,
                ] : null,
                'date_of_works' => $u->date_of_works,
            ]),
            'meta' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single marketing employee.
     * GET /api/marketing/employees/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['team'])
            ->where('type', 'marketing')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات الموظف بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'job_title' => $user->job_title,
                'department' => $user->department,
                'is_active' => $user->is_active,
                'is_manager' => $user->is_manager,
                'is_executive_director' => (bool) $user->is_executive_director,
                'date_of_works' => $user->date_of_works,
                'team' => $user->team ? [
                    'id' => $user->team->id,
                    'name' => $user->team->name,
                ] : null,
            ],
        ]);
    }
}
