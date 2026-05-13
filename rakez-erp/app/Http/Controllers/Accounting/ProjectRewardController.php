<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\GenerateProjectRewardRequest;
use App\Http\Requests\Accounting\RejectProjectRewardRequest;
use App\Http\Resources\Accounting\ProjectRewardResource;
use App\Models\ProjectReward;
use App\Models\SalesReservation;
use App\Services\Accounting\ProjectRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRewardController extends Controller
{
    public function __construct(
        private ProjectRewardService $rewardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-rewards.view'), 403);

        $request->validate([
            'contract_id' => ['nullable', 'exists:contracts,id'],
            'sales_reservation_id' => ['nullable', 'exists:sales_reservations,id'],
            'status' => ['nullable', 'in:pending,approved,rejected,paid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->rewardService->index($request->only([
            'contract_id',
            'sales_reservation_id',
            'status',
            'per_page',
        ]));

        return response()->json([
            'success' => true,
            'data' => ProjectRewardResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, ProjectReward $projectReward): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-rewards.view'), 403);

        $projectReward->load([
            'contract',
            'salesReservation',
            'recipients.user',
            'creator',
            'approver',
            'payer',
        ]);

        return response()->json([
            'success' => true,
            'data' => new ProjectRewardResource($projectReward),
        ]);
    }

    public function generate(
        GenerateProjectRewardRequest $request,
        SalesReservation $reservation,
    ): JsonResponse {
        $reward = $this->rewardService->generate(
            $reservation,
            $request->user(),
            $request->generationOptions(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Project reward generated.',
            'data' => new ProjectRewardResource($reward),
        ], 201);
    }

    public function approve(Request $request, ProjectReward $projectReward): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-rewards.approve'), 403);

        $reward = $this->rewardService->approve($projectReward, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Project reward approved.',
            'data' => new ProjectRewardResource($reward),
        ]);
    }

    public function reject(RejectProjectRewardRequest $request, ProjectReward $projectReward): JsonResponse
    {
        $reward = $this->rewardService->reject(
            $projectReward,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Project reward rejected.',
            'data' => new ProjectRewardResource($reward),
        ]);
    }

    public function markPaid(Request $request, ProjectReward $projectReward): JsonResponse
    {
        abort_unless($request->user()->hasPermissionTo('accounting.project-rewards.pay'), 403);

        $reward = $this->rewardService->markAsPaid($projectReward, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Project reward marked as paid.',
            'data' => new ProjectRewardResource($reward),
        ]);
    }
}
