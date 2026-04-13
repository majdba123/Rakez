<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\PrepareAssistantDraftRequest;
use App\Services\AI\Drafts\AssistantDraftService;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantDraftController extends Controller
{
    public function __construct(
        private readonly AssistantDraftService $draftService,
    ) {}

    public function flows(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->draftService->catalog($request->user()),
        ]);
    }

    public function prepare(PrepareAssistantDraftRequest $request): JsonResponse
    {
        try {
            $payload = $this->draftService->prepare(
                $request->user(),
                (string) $request->input('message'),
                $request->input('flow'),
                $request->input('provider')
            );

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (AiAssistantException $exception) {
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }
}
