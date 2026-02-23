<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ExplainAccessRequest;
use App\Http\Requests\AI\RakizChatRequest;
use App\Http\Requests\AI\RakizSearchRequest;
use App\Models\AIConversation;
use App\Services\AI\AccessExplanationEngine;
use App\Services\AI\AiRagService;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
        $sessionId = $this->resolveSessionId($request->input('session_id'));
        $pageContext = $request->input('page_context', []);

        $output = $this->orchestrator->chat($user, $message, $sessionId, $pageContext);
        $output['session_id'] = $sessionId;
        $this->storeV2Turn($user->id, $sessionId, $message, $output);

        return response()->json([
            'success' => true,
            'data' => $output,
        ]);
    }

    /**
     * POST /api/ai/v2/chat/stream – SSE streaming wrapper around the orchestrator.
     *
     * Event sequence:
     *   event: start   → { session_id }
     *   event: delta   → partial markdown chunks
     *   event: sources → sources array
     *   event: done    → full structured payload
     */
    public function chatStream(RakizChatRequest $request): StreamedResponse|JsonResponse
    {
        if (! config('ai_assistant.v2.enabled', true)) {
            return response()->json(['message' => 'AI assistant is disabled'], 503);
        }

        $user = $request->user();
        $message = (string) $request->input('message');
        $sessionId = $this->resolveSessionId($request->input('session_id'));
        $pageContext = $request->input('page_context', []);

        return new StreamedResponse(function () use ($user, $message, $sessionId, $pageContext) {
            $this->sseEvent('start', [
                'session_id' => $sessionId,
                'status' => 'processing',
            ]);

            try {
                $output = $this->orchestrator->chat($user, $message, $sessionId, $pageContext);
                $output['session_id'] = $sessionId;
                $this->storeV2Turn($user->id, $sessionId, $message, $output);

                $markdown = $output['answer_markdown'] ?? '';
                $chunkSize = 80;
                $offset = 0;
                $len = mb_strlen($markdown);

                while ($offset < $len) {
                    $chunk = mb_substr($markdown, $offset, $chunkSize);
                    $this->sseEvent('delta', ['chunk' => $chunk]);
                    $offset += $chunkSize;
                }

                if (! empty($output['sources'])) {
                    $this->sseEvent('sources', ['sources' => $output['sources']]);
                }

                $this->sseEvent('done', [
                    'success' => true,
                    'data' => $output,
                ]);
            } catch (Throwable $e) {
                $this->sseEvent('error', [
                    'message' => 'حدث خطأ أثناء المعالجة. يرجى المحاولة لاحقاً.',
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * GET /api/ai/v2/conversations – list v2 sessions for current user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $latestIds = AIConversation::query()
            ->select(DB::raw('MAX(id) as id'))
            ->where('user_id', $user->id)
            ->where('section', 'rakiz_v2')
            ->groupBy('session_id');

        $sessions = AIConversation::query()
            ->whereIn('id', $latestIds)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $data = collect($sessions->items())->map(fn (AIConversation $row) => [
            'session_id' => $row->session_id,
            'last_message' => $row->message,
            'last_role' => $row->role,
            'last_message_at' => optional($row->created_at)->toDateTimeString(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $sessions->total(),
                'count' => $sessions->count(),
                'per_page' => $sessions->perPage(),
                'current_page' => $sessions->currentPage(),
                'total_pages' => $sessions->lastPage(),
                'has_more_pages' => $sessions->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/ai/v2/conversations/{sessionId}/messages – full message history.
     */
    public function messages(Request $request, string $sessionId): JsonResponse
    {
        if (! Str::isUuid($sessionId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session id.',
            ], 422);
        }

        $user = $request->user();
        $messages = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('section', 'rakiz_v2')
            ->where('session_id', $sessionId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $data = $messages->map(fn (AIConversation $row) => [
            'id' => $row->id,
            'session_id' => $row->session_id,
            'role' => $row->role,
            'message' => $row->message,
            'created_at' => optional($row->created_at)->toDateTimeString(),
            'metadata' => $row->metadata,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * DELETE /api/ai/v2/conversations/{sessionId} – delete a v2 conversation.
     */
    public function deleteSession(Request $request, string $sessionId): JsonResponse
    {
        if (! Str::isUuid($sessionId)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid session id.',
            ], 422);
        }

        $user = $request->user();
        $deleted = AIConversation::query()
            ->where('user_id', $user->id)
            ->where('section', 'rakiz_v2')
            ->where('session_id', $sessionId)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['deleted' => $deleted > 0],
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

    private function sseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    private function resolveSessionId(?string $candidate): string
    {
        if (is_string($candidate) && Str::isUuid($candidate)) {
            return $candidate;
        }

        return (string) Str::uuid();
    }

    private function storeV2Turn(int $userId, string $sessionId, string $userMessage, array $output): void
    {
        $assistantMessage = (string) ($output['answer_markdown'] ?? '');

        AIConversation::query()->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => 'user',
            'message' => $userMessage,
            'section' => 'rakiz_v2',
            'metadata' => ['channel' => 'rakiz_v2'],
        ]);

        AIConversation::query()->create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'message' => $assistantMessage,
            'section' => 'rakiz_v2',
            'metadata' => [
                'channel' => 'rakiz_v2',
                'confidence' => $output['confidence'] ?? null,
                'sources_count' => count($output['sources'] ?? []),
            ],
        ]);
    }
}
