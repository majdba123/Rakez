<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreProjectRewardSettingRequest;
use App\Http\Requests\Accounting\UpdateProjectRewardSettingRequest;
use App\Http\Resources\Accounting\ProjectRewardSettingResource;
use App\Models\ProjectRewardSetting;
use App\Services\Accounting\ProjectRewardSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRewardSettingController extends Controller
{
    public function __construct(
        private ProjectRewardSettingService $settingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-reward-settings.view'), 403);

        $request->validate([
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->settingService->index([
            'contract_id' => $request->query('contract_id'),
            'per_page' => $request->query('per_page'),
        ]);

        return response()->json([
            'success' => true,
            'data' => ProjectRewardSettingResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreProjectRewardSettingRequest $request): JsonResponse
    {
        $model = $this->settingService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Project reward setting created.',
            'data' => new ProjectRewardSettingResource($model),
        ], 201);
    }

    public function show(Request $request, ProjectRewardSetting $projectRewardSetting): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-reward-settings.view'), 403);

        $projectRewardSetting->load([
            'contract',
            'creator',
            'ceoUser',
            'salesManagerUser',
            'salesLeaderUser',
            'groupLeaderUser',
            'externalMarketerUser',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProjectRewardSettingResource($projectRewardSetting),
        ]);
    }

    public function update(UpdateProjectRewardSettingRequest $request, ProjectRewardSetting $projectRewardSetting): JsonResponse
    {
        $model = $this->settingService->update($projectRewardSetting, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Project reward setting updated.',
            'data' => new ProjectRewardSettingResource($model),
        ]);
    }

    public function activate(Request $request, ProjectRewardSetting $projectRewardSetting): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-reward-settings.manage'), 403);

        $model = $this->settingService->activate($projectRewardSetting);

        return response()->json([
            'success' => true,
            'message' => 'Project reward setting activated.',
            'data' => new ProjectRewardSettingResource($model),
        ]);
    }
}
