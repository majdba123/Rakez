<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AskQuestionRequest;
use App\Http\Requests\AI\ChatRequest;
use App\Http\Resources\AI\ChatResource;
use App\Http\Resources\AI\ConversationResource;
use App\Events\AI\AiRequestFailed;
use App\Services\AI\AIAssistantService;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIAssistantController extends Controller
{
    public function __construct(private readonly AIAssistantService $assistantService) {}

    public function ask(AskQuestionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $payload = $this->assistantService->ask(
                (string) $request->input('question'),
                $user,
                $request->input('section'),
                $request->input('context', [])
            );

            $payload['suggestions'] = $this->assistantService->suggestions($request->input('section'));

            return response()->json([
                'success' => true,
                'data' => new ChatResource($payload),
            ]);
        } catch (AiAssistantException $exception) {
            $this->dispatchAiRequestFailed($request->user()->id, $request->input('session_id'), $exception);

            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    public function chat(ChatRequest $request): JsonResponse|StreamedResponse
    {
        if ($request->boolean('stream')) {
            return $this->streamChat($request);
        }

        try {
            $user = $request->user();
            $payload = $this->assistantService->chat(
                (string) $request->input('message'),
                $user,
                $request->input('session_id'),
                $request->input('section'),
                $request->input('context', [])
            );

            $payload['suggestions'] = $this->assistantService->suggestions($request->input('section'));

            return response()->json([
                'success' => true,
                'data' => new ChatResource($payload),
            ]);
        } catch (AiAssistantException $exception) {
            $this->dispatchAiRequestFailed($request->user()->id, $request->input('session_id'), $exception);

            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    private function streamChat(ChatRequest $request): StreamedResponse
    {
        $user = $request->user();
        $message = (string) $request->input('message');
        $sessionId = $request->input('session_id');
        $section = $request->input('section');
        $context = $request->input('context', []);

        return new StreamedResponse(function () use ($message, $user, $sessionId, $section, $context) {
            try {
                foreach ($this->assistantService->streamChat($message, $user, $sessionId, $section, $context) as $sseChunk) {
                    echo $sseChunk;
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (AiAssistantException $exception) {
                $this->dispatchAiRequestFailed($user->id, $sessionId, $exception);

                echo 'data: ' . json_encode([
                    'error' => true,
                    'error_code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            } catch (\Throwable $exception) {
                echo 'data: ' . json_encode([
                    'error' => true,
                    'message' => 'An unexpected error occurred.',
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = (int) $request->query('per_page', 20);
            $section = $request->query('section');

            $sessions = $this->assistantService->listSessions($user, $section, $perPage);

            return response()->json([
                'success' => true,
                'data' => ConversationResource::collection($sessions),
                'meta' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                ],
            ]);
        } catch (AiAssistantException $exception) {
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    public function deleteSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            $deleted = $this->assistantService->deleteSession($user, $sessionId);

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted' => $deleted,
                ],
            ]);
        } catch (AiAssistantException $exception) {
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    public function sections(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sections = $this->assistantService->availableSections($user);

            return response()->json([
                'success' => true,
                'data' => $sections,
            ]);
        } catch (AiAssistantException $exception) {
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    private function dispatchAiRequestFailed(int $userId, ?string $sessionId, AiAssistantException $exception): void
    {
        $request = request();
        $correlationId = $request->header('X-Request-Id')
            ?? $request->header('X-Request-ID')
            ?? $request->header('X-Correlation-Id')
            ?? $request->header('X-Correlation-ID');

        event(new AiRequestFailed(
            userId: $userId,
            sessionId: $sessionId,
            error: $exception->getMessage(),
            attempts: 1,
            correlationId: $correlationId,
        ));
    }
}
