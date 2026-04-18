<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ConfirmSafeWriteActionRequest;
use App\Http\Requests\AI\PreviewSafeWriteActionRequest;
use App\Http\Requests\AI\ProposeSafeWriteActionRequest;
use App\Http\Requests\AI\RejectSafeWriteActionRequest;
use App\Services\AI\SafeWrites\SafeWriteActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafeWriteActionController extends Controller
{
    public function __construct(
        private readonly SafeWriteActionService $service,
    ) {}

    public function catalog(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'submit_mode' => 'manual_only',
            'execution_enabled' => false,
            'actions' => $this->service->catalog($request->user()),
        ]);
    }

    public function propose(ProposeSafeWriteActionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->propose(
                $request->user(),
                (string) $request->validated('action_key'),
                $request->validated()
            ),
        ]);
    }

    public function preview(PreviewSafeWriteActionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->preview(
                $request->user(),
                (string) $request->validated('action_key'),
                $request->validated()
            ),
        ]);
    }

    public function confirm(ConfirmSafeWriteActionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->confirm(
                $request->user(),
                (string) $request->validated('action_key'),
                $request->validated()
            ),
        ]);
    }

    public function reject(RejectSafeWriteActionRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->reject(
                $request->user(),
                (string) $request->validated('action_key'),
                $request->validated()
            ),
        ]);
    }
}
