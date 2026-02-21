<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ExplainAccessRequest;
use App\Http\Requests\AI\RakizChatRequest;
use App\Http\Requests\AI\RakizSearchRequest;
use App\Services\AI\AccessExplanationEngine;
use App\Services\AI\AiRagService;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Http\JsonResponse;

class RakizV2Controller extends Controller
{
    public function __construct(
        private readonly RakizAiOrchestrator $orchestrator,
        private readonly AiRagService $ragService,
        private readonly AccessExplanationEngine $accessEngine
    ) {}

    /**
     * POST /api/ai/v2/chat – main orchestrated chat; strict JSON response.
     */
    public function chat(RakizChatRequest $request): JsonResponse
    {
        if (! config('ai_assistant.v2.enabled', true)) {
            return response()->json(['message' => 'AI assistant is disabled'], 503);
        }

        $user = $request->user();
        $message = (string) $request->input('message');
        $sessionId = $request->input('session_id');
        $pageContext = $request->input('page_context', []);

        $output = $this->orchestrator->chat($user, $message, $sessionId, $pageContext);

        return response()->json([
            'success' => true,
            'data' => $output,
        ]);
    }

    /**
     * POST /api/ai/v2/search – RAG sources only.
     */
    public function search(RakizSearchRequest $request): JsonResponse
    {
        if (! config('ai_assistant.v2.enabled', true)) {
            return response()->json(['message' => 'AI assistant is disabled'], 503);
        }

        $user = $request->user();
        $query = (string) $request->input('query');
        $filters = $request->input('filters', []);
        $limit = min(20, max(1, (int) $request->input('limit', 10)));

        $sources = $this->ragService->search($user, $query, $filters, $limit);

        return response()->json([
            'success' => true,
            'data' => ['sources' => $sources],
        ]);
    }

    /**
     * POST /api/ai/v2/explain-access – route/record access explanation.
     */
    public function explainAccess(ExplainAccessRequest $request): JsonResponse
    {
        $user = $request->user();
        $route = (string) $request->input('route');
        $entityType = $request->input('entity_type');
        $entityId = $request->has('entity_id') ? (int) $request->input('entity_id') : null;

        $result = $this->accessEngine->explainAccess($user, $route, $entityType, $entityId);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
