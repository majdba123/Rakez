<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\AssignExecutiveDirectorLineTeamsRequest;
use App\Http\Requests\Sales\StoreExecutiveDirectorLineRequest;
use App\Http\Requests\Sales\UpdateExecutiveDirectorLineRequest;
use App\Http\Resources\Sales\ExecutiveDirectorLineResource;
use App\Models\ExecutiveDirectorLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveDirectorLineController extends Controller
{
    /**
     * List ExecutiveDirectorLine rows (not sales targets).
     * Allowed for admin, or for sales employees with is_manager = true (no extra route middleware/permission).
     * GET /api/sales/executive/targets — query: from, to (created_at), status, line_type, per_page
     */
    public function executiveTargets(Request $request): JsonResponse
    {
        $user = $request->user();
        $allowed = $user && ($user->isAdmin() || $user->hasRole('admin') || $user->isSalesTeamManager());
        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - الإدمن أو من نوع مبيعات ومدير (sales + is_manager) فقط.',
            ], 403);
        }

        try {
            $perPage = min((int) $request->query('per_page', 20), 100);

            $query = ExecutiveDirectorLine::query()
                ->with('teams')
                ->orderByDesc('id');

            if ($request->filled('team_id')) {
                $teamId = (int) $request->query('team_id');
                $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
            if ($request->filled('line_type')) {
                $query->where('line_type', 'like', '%'.addcslashes((string) $request->query('line_type'), '%_\\').'%');
            }
            if ($request->filled('from')) {
                $query->whereDate('created_at', '>=', $request->query('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('created_at', '<=', $request->query('to'));
            }

            $rows = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ExecutiveDirectorLineResource::collection($rows->items()),
                'meta' => [
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب السطور: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all lines (no sales target).
     * GET /api/sales/executive-director-lines
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $rows = ExecutiveDirectorLine::query()
            ->with('teams')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ExecutiveDirectorLineResource::collection($rows->items()),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /**
     * POST /api/sales/executive-director-lines
     * Body: line_type, value, status (status defaults to pending)
     */
    public function store(StoreExecutiveDirectorLineRequest $request): JsonResponse
    {
        $v = $request->validated();
        $row = ExecutiveDirectorLine::query()->create([
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
            'status' => $v['status'] ?? 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load('teams')),
        ], 201);
    }

    /**
     * Replace team assignments (one or many teams). Admin or sales manager (sales + is_manager).
     * PUT /api/sales/executive-director-lines/{id}/teams — body: { "team_ids": [1,2,3] } (empty to clear)
     */
    public function syncTeams(AssignExecutiveDirectorLineTeamsRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $allowed = $user && ($user->isAdmin() || $user->hasRole('admin') || $user->isSalesTeamManager());
        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح - الإدمن أو من نوع مبيعات ومدير (sales + is_manager) فقط.',
            ], 403);
        }


        $row = ExecutiveDirectorLine::query()->find($id);
        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'سطر المدير التنفيذي غير موجود.',
            ], 404);
        }

        $ids = array_values(array_unique(array_map('intval', $request->validated('team_ids'))));
        $row->teams()->sync($ids);

        return response()->json([
            'success' => true,
            'message' => 'تم ربط الفرق بالسطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load('teams')),
        ]);
    }

    /**
     * GET /api/sales/executive-director-lines/{id}
     */
    public function show(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->with('teams')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new ExecutiveDirectorLineResource($row),
        ]);
    }

    /**
     * PUT /api/sales/executive-director-lines/{id}
     * Body: line_type, value, status (status optional; omit to keep current)
     */
    public function update(UpdateExecutiveDirectorLineRequest $request, int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $v = $request->validated();
        $payload = [
            'line_type' => $v['line_type'],
            'value' => $v['value'] ?? null,
        ];
        if (array_key_exists('status', $v) && $v['status'] !== null) {
            $payload['status'] = $v['status'];
        }
        $row->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السطر.',
            'data' => new ExecutiveDirectorLineResource($row->fresh()->load('teams')),
        ]);
    }

    /**
     * DELETE /api/sales/executive-director-lines/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $row = ExecutiveDirectorLine::query()->findOrFail($id);
        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السطر.',
        ]);
    }
}
