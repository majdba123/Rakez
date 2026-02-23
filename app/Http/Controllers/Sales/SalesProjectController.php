<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\UpdateEmergencyContactsRequest;
use App\Http\Resources\Sales\SalesProjectDetailResource;
use App\Http\Resources\Sales\SalesProjectResource;
use App\Http\Resources\Sales\SalesUnitResource;
use App\Models\User;
use App\Services\Sales\SalesProjectService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesProjectController extends Controller
{
    public function __construct(
        private SalesProjectService $projectService
    ) {}

    /**
     * List sales projects.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'q' => $request->query('q'),
            'city' => $request->query('city'),
            'district' => $request->query('district'),
            'scope' => $request->query('scope', 'me'),
            'per_page' => ApiResponse::getPerPage($request, 15, 100),
        ];
        $projects = $this->projectService->listProjects($filters, $request->user());
        return ApiResponse::success(
            SalesProjectResource::collection($projects->items()),
            'تم جلب قائمة المشاريع بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($projects)]
        );
    }

    /**
     * Show project details.
     */
    public function show(int $contractId): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectById($contractId);
            return ApiResponse::success(new SalesProjectDetailResource($project), 'تم جلب بيانات المشروع بنجاح');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::notFound('المشروع غير موجود');
        } catch (\Exception $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * Get project units.
     */
    public function units(Request $request, int $contractId): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'floor' => $request->query('floor'),
            'min_price' => $request->query('min_price'),
            'max_price' => $request->query('max_price'),
            'per_page' => ApiResponse::getPerPage($request, 15, 100),
        ];
        $units = $this->projectService->listUnits($contractId, $filters);
        return ApiResponse::success(
            SalesUnitResource::collection($units->items()),
            'تم جلب الوحدات بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($units)]
        );
    }

    /**
     * List team projects (leader only).
     */
    public function teamProjects(Request $request): JsonResponse
    {
        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $projects = $this->projectService->listTeamProjectsPaginated($request->user(), $perPage);
        return ApiResponse::success(
            SalesProjectResource::collection($projects->items()),
            'تم جلب مشاريع الفريق بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($projects)]
        );
    }

    /**
     * List team members (leader only).
     */
    public function teamMembers(Request $request): JsonResponse
    {
        $user = $request->user();
        $members = User::where('type', 'sales')
            ->where('team', $user->team)
            ->where('id', '!=', $user->id)
            ->select('id', 'name', 'email', 'team')
            ->get();
        return ApiResponse::success($members, 'تم جلب أعضاء الفريق بنجاح');
    }

    /**
     * Update project emergency contacts (leader only).
     */
    public function updateEmergencyContacts(
        UpdateEmergencyContactsRequest $request,
        int $contractId
    ): JsonResponse {
        try {
            $project = $this->projectService->updateEmergencyContacts(
                $contractId,
                $request->validated(),
                $request->user()
            );
            return ApiResponse::success([
                'contract_id' => $project->id,
                'emergency_contact_number' => $project->emergency_contact_number,
                'security_guard_number' => $project->security_guard_number,
            ], 'تم تحديث جهات الاتصال للطوارئ بنجاح');
        } catch (\Exception $e) {
            return ApiResponse::forbidden($e->getMessage());
        }
    }

    /**
     * Assign project to leader (admin only).
     */
    public function assignProject(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'leader_id' => 'required|exists:users,id',
                'contract_id' => 'required|exists:contracts,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);
            $assignment = $this->projectService->assignProjectToLeader(
                $validated['leader_id'],
                $validated['contract_id'],
                $request->user()->id,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );
            return ApiResponse::created($assignment, 'تم تعيين المشروع بنجاح');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'تاريخ') || str_contains($e->getMessage(), 'تعيين') ? 400 : 500;
            return ApiResponse::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Get my assignments (for sales leaders).
     */
    public function getMyAssignments(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = ApiResponse::getPerPage($request, 15, 100);
        $assignments = \App\Models\SalesProjectAssignment::where('leader_id', $user->id)
            ->with(['contract', 'assignedBy'])
            ->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        $assignmentsData = $assignments->getCollection()->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'contract_id' => $assignment->contract_id,
                'project_name' => $assignment->contract->project_name ?? 'N/A',
                'start_date' => $assignment->start_date?->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
                'is_active' => $assignment->isActive(),
                'assigned_by' => $assignment->assignedBy->name ?? 'N/A',
                'created_at' => $assignment->created_at?->toIso8601String(),
            ];
        });
        return ApiResponse::success(
            $assignmentsData->values()->all(),
            'تم جلب التعيينات بنجاح',
            200,
            ['pagination' => ApiResponse::paginationMeta($assignments)]
        );
    }
}
