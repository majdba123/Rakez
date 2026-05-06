<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreProjectCommissionSettingRequest;
use App\Http\Requests\Accounting\UpdateProjectCommissionSettingRequest;
use App\Http\Resources\Accounting\ProjectCommissionSettingResource;
use App\Models\ProjectCommissionSetting;
use App\Services\Accounting\ProjectCommissionSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectCommissionSettingController extends Controller
{
    public function __construct(
        private ProjectCommissionSettingService $settingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionTo('accounting.sold-units.view')
                || $request->user()->hasPermissionTo('accounting.sold-units.manage'),
            403
        );

        $request->validate([
            'project_id' => 'nullable|exists:contracts,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $paginator = $this->settingService->index([
            'project_id' => $request->query('project_id'),
            'per_page' => $request->query('per_page'),
        ]);

        return response()->json([
            'success' => true,
            'data' => ProjectCommissionSettingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreProjectCommissionSettingRequest $request): JsonResponse
    {
        $model = $this->settingService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Project commission setting created.',
            'data' => new ProjectCommissionSettingResource($model),
        ], 201);
    }

    public function show(Request $request, ProjectCommissionSetting $projectCommissionSetting): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionTo('accounting.sold-units.view')
                || $request->user()->hasPermissionTo('accounting.sold-units.manage'),
            403
        );

        $projectCommissionSetting->load([
            'project',
            'creator',
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProjectCommissionSettingResource($projectCommissionSetting),
        ]);
    }

    public function update(UpdateProjectCommissionSettingRequest $request, ProjectCommissionSetting $projectCommissionSetting): JsonResponse
    {
        $model = $this->settingService->update($projectCommissionSetting, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project commission setting updated.',
            'data' => new ProjectCommissionSettingResource($model),
        ]);
    }

    public function activate(Request $request, ProjectCommissionSetting $projectCommissionSetting): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.sold-units.manage'), 403);

        $model = $this->settingService->activate($projectCommissionSetting);

        return response()->json([
            'success' => true,
            'message' => 'Project commission setting activated.',
            'data' => new ProjectCommissionSettingResource($model),
        ]);
    }
}
