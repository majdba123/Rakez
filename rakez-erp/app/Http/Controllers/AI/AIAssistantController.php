<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AskQuestionRequest;
use App\Http\Requests\AI\ChatRequest;
use App\Http\Resources\AI\ChatResource;
use App\Http\Resources\AI\ConversationResource;
use App\Services\AI\AIAssistantService;
use App\Services\AI\Exceptions\AiAssistantException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
    }

    public function chat(ChatRequest $request): JsonResponse
    {
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
            return response()->json([
                'success' => false,
                'error_code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        }
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
}
