<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\UpdateEmergencyContactsRequest;
use App\Http\Resources\Sales\SalesProjectDetailResource;
use App\Http\Resources\Sales\SalesProjectResource;
use App\Http\Resources\Sales\SalesUnitResource;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\User;
use App\Services\Pdf\PdfFactory;
use App\Services\Sales\SalesProjectService;
use App\Services\Sales\SalesTeamService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SalesProjectController extends Controller
{
    public function __construct(
        private SalesProjectService $projectService,
        private SalesTeamService $teamService
    ) {}

    /**
     * List sales projects.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            // مدير مبيعات: افتراضياً كل المشاريع المكتملة (بما فيها غير المُسنَدة) لتمكين الإسناد. موظف مبيعات: مشاريع الفريق.
            $defaultScope = $user->isSalesLeader() ? 'all' : 'me';
            $filters = [
                'status' => $request->query('status'),
                'q' => $request->query('q'),
                'city_id' => $request->query('city_id'),
                'district_id' => $request->query('district_id'),
                'scope' => $request->query('scope', $defaultScope),
                'per_page' => $request->query('per_page', 15),
            ];

            $projects = $this->projectService->listProjects($filters, $request->user());

            return response()->json([
                'success' => true,
                'data' => SalesProjectResource::collection($projects->items()),
                'meta' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show project details.
     */
    public function show(Request $request, int $contractId): JsonResponse
    {
        try {
            if (!$this->projectService->userCanAccessContract($request->user(), $contractId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }
            $project = $this->projectService->getProjectById($contractId);

            return response()->json([
                'success' => true,
                'data' => new SalesProjectDetailResource($project),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project units.
     */
    public function units(Request $request, int $contractId): JsonResponse
    {
        try {
            if (!$this->projectService->userCanAccessContract($request->user(), $contractId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }
            $filters = [
                'status' => $request->query('status'),
                'floor' => $request->query('floor'),
                'min_price' => $request->query('min_price'),
                'max_price' => $request->query('max_price'),
                'per_page' => $request->query('per_page', 15),
            ];

            $units = $this->projectService->listUnits($contractId, $filters);

            return response()->json([
                'success' => true,
                'data' => SalesUnitResource::collection($units->items()),
                'meta' => [
                    'current_page' => $units->currentPage(),
                    'last_page' => $units->lastPage(),
                    'per_page' => $units->perPage(),
                    'total' => $units->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve units: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unit PDF data (تفاصيل وحدة). JSON only; frontend uses generateUnitDetailsPdf(unit, { projectName }).
     * GET /api/sales/units/{unitId}/pdf-data
     */
    public function unitPdfData(Request $request, int $unitId): JsonResponse
    {
        try {
            $unit = ContractUnit::with('contract')->findOrFail($unitId);
            $contract = $unit->contract;

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit has no associated project',
                ], 404);
            }

            if (!$this->projectService->userCanAccessContract($request->user(), $contract->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this unit',
                ], 403);
            }

            $totalArea = (float) ($unit->area ?? 0) + (float) ($unit->private_area_m2 ?? 0);
            $price = $unit->price !== null ? (float) $unit->price : 0;

            $data = [
                'unit' => [
                    'unit_number' => (string) ($unit->unit_number ?? ''),
                    'id' => $unit->id,
                    'status' => (string) ($unit->status ?? 'available'),
                    'floor' => $unit->floor !== null ? (int) $unit->floor : 0,
                    'area' => $unit->area !== null ? (float) $unit->area : 0,
                    'private_area' => $unit->private_area_m2 !== null ? (float) $unit->private_area_m2 : 0,
                    'total_area' => $totalArea,
                    'bedrooms' => $unit->bedrooms !== null ? (int) $unit->bedrooms : 0,
                    'rooms' => ($unit->bedrooms ?? 0) + ($unit->bathrooms ?? 0),
                    'facade' => (string) ($unit->view ?? ''),
                    'view' => (string) ($unit->view ?? ''),
                    'price' => $price,
                    'total_price' => $price,
                ],
                'projectName' => (string) ($contract->project_name ?? ''),
            ];

            return response()->json($data, 200, ['Content-Type' => 'application/json']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Unit not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download unit details as PDF.
     * GET /api/sales/units/{id}/pdf
     */
    public function unitPdf(int $id): Response|JsonResponse
    {
        try {
            $unit = ContractUnit::with('contract')->findOrFail($id);
            $contract = $unit->contract;

            if (!$contract) {
                return response()->json([
                    'success' => false,
                    'message' => 'تحميل PDF غير متوفر لهذه الوحدة حالياً',
                    'reason' => 'Unit has no associated contract.',
                ], 404);
            }

            $filename = sprintf('unit_%s_%s.pdf', preg_replace('/[^a-zA-Z0-9\-_]/', '_', (string) ($unit->unit_number ?? $id)), now()->format('Y-m-d'));

            return PdfFactory::download('pdfs.unit_details', [
                'unit' => $unit,
                'contract' => $contract,
                'generated_at' => now()->format('Y-m-d H:i'),
            ], $filename);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'تحميل PDF غير متوفر لهذه الوحدة حالياً',
            ], 503);
        }
    }

    /**
     * List team projects (leader only).
     */
    public function teamProjects(Request $request): JsonResponse
    {
        try {
            $perPage = ApiResponse::getPerPage($request);
            $projects = $this->projectService->listTeamProjectsPaginated($request->user(), $perPage);

            return response()->json([
                'success' => true,
                'data' => SalesProjectResource::collection($projects->items()),
                'meta' => [
                    'pagination' => [
                        'total' => $projects->total(),
                        'count' => $projects->count(),
                        'per_page' => $projects->perPage(),
                        'current_page' => $projects->currentPage(),
                        'total_pages' => $projects->lastPage(),
                        'has_more_pages' => $projects->hasMorePages(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List team members (leader only). Includes leader rating and confirmed reservations count.
     */
    public function teamMembers(Request $request): JsonResponse
    {
        try {
            $withRatings = $request->boolean('with_ratings', true);
            $members = $withRatings
                ? $this->teamService->getTeamMembersWithRatings($request->user())
                : $this->teamService->getTeamMembers($request->user())->map(fn (User $u) => [
                    'user' => $u,
                    'leader_rating' => null,
                    'leader_rating_comment' => null,
                    'confirmed_reservations_count' => 0,
                ]);
            $data = $members->map(fn (array $item) => $this->teamService->memberToApiShape($item, false))->values();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team members: ' . $e->getMessage(),
            ], 500);
        }
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

            return response()->json([
                'success' => true,
                'message' => 'Emergency contacts updated successfully',
                'data' => [
                    'contract_id' => $project->id,
                    'emergency_contact_number' => $project->emergency_contact_number,
                    'security_guard_number' => $project->security_guard_number,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency contacts: ' . $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Assign project to a sales team by team code (admin only). Resolves the team's sales leader internally.
     */
    public function assignProject(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'team_code' => 'required|string|max:32',
                'contract_id' => 'required|exists:contracts,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $assignment = $this->projectService->assignProjectToTeamByCode(
                $validated['team_code'],
                $validated['contract_id'],
                $request->user()->id,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Project assigned successfully',
                'data' => $assignment->loadMissing(['leader', 'contract']),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $statusCode = 500;
            if (str_contains($e->getMessage(), 'تاريخ') || str_contains($e->getMessage(), 'تعيين')) {
                $statusCode = 400;
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign project: ' . $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get my assignments (for sales leaders).
     */
    public function getMyAssignments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = ApiResponse::getPerPage($request);

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

            return response()->json([
                'success' => true,
                'data' => $assignmentsData->values()->all(),
                'meta' => [
                    'pagination' => [
                        'total' => $assignments->total(),
                        'count' => $assignments->count(),
                        'per_page' => $assignments->perPage(),
                        'current_page' => $assignments->currentPage(),
                        'total_pages' => $assignments->lastPage(),
                        'has_more_pages' => $assignments->hasMorePages(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignments: ' . $e->getMessage(),
            ], 500);
        }
    }
}
