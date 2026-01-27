<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\UpdateEmergencyContactsRequest;
use App\Http\Resources\Sales\SalesProjectDetailResource;
use App\Http\Resources\Sales\SalesProjectResource;
use App\Http\Resources\Sales\SalesUnitResource;
use App\Models\Contract;
use App\Models\User;
use App\Services\Sales\SalesProjectService;
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
        try {
            $filters = [
                'status' => $request->query('status'),
                'q' => $request->query('q'),
                'city' => $request->query('city'),
                'district' => $request->query('district'),
                'scope' => $request->query('scope', 'me'),
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
    public function show(int $contractId): JsonResponse
    {
        try {
            $project = $this->projectService->getProjectById($contractId);

            return response()->json([
                'success' => true,
                'data' => new SalesProjectDetailResource($project),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get project units.
     */
    public function units(Request $request, int $contractId): JsonResponse
    {
        try {
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
     * List team projects (leader only).
     */
    public function teamProjects(Request $request): JsonResponse
    {
        try {
            $projects = $this->projectService->listTeamProjects($request->user());

            return response()->json([
                'success' => true,
                'data' => SalesProjectResource::collection($projects),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve team projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List team members (leader only).
     */
    public function teamMembers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $members = User::where('type', 'sales')
                ->where('team', $user->team)
                ->where('id', '!=', $user->id)
                ->select('id', 'name', 'email', 'team')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);
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
     * Assign project to leader (admin only).
     */
    public function assignProject(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'leader_id' => 'required|exists:users,id',
                'contract_id' => 'required|exists:contracts,id',
            ]);

            $assignment = $this->projectService->assignProjectToLeader(
                $validated['leader_id'],
                $validated['contract_id'],
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Project assigned successfully',
                'data' => $assignment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign project: ' . $e->getMessage(),
            ], 500);
        }
    }
}
