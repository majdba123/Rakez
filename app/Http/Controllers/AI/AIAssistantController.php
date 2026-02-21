<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\AskQuestionRequest;
use App\Http\Requests\AI\ChatRequest;
use App\Http\Resources\AI\ChatResource;
use App\Http\Resources\AI\ConversationResource;
use App\Http\Responses\ApiResponse;
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

            return ApiResponse::success(new ChatResource($payload));
        } catch (AiAssistantException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->statusCode(), $exception->errorCode());
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

            return ApiResponse::success(new ChatResource($payload));
        } catch (AiAssistantException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->statusCode(), $exception->errorCode());
        }
    }

    public function conversations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = ApiResponse::getPerPage($request, 20, 100);
            $section = $request->query('section');

            $sessions = $this->assistantService->listSessions($user, $section, $perPage);

            return ApiResponse::success(ConversationResource::collection($sessions)->resolve(), 'تمت العملية بنجاح', 200, [
                'pagination' => [
                    'total' => $sessions->total(),
                    'count' => $sessions->count(),
                    'per_page' => $sessions->perPage(),
                    'current_page' => $sessions->currentPage(),
                    'total_pages' => $sessions->lastPage(),
                    'has_more_pages' => $sessions->hasMorePages(),
                ],
            ]);
        } catch (AiAssistantException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->statusCode(), $exception->errorCode());
        }
    }

    public function deleteSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            $deleted = $this->assistantService->deleteSession($user, $sessionId);

            return ApiResponse::success(['deleted' => $deleted]);
        } catch (AiAssistantException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->statusCode(), $exception->errorCode());
        }
    }

    public function sections(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sections = $this->assistantService->availableSections($user);

            return ApiResponse::success($sections);
        } catch (AiAssistantException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->statusCode(), $exception->errorCode());
        }
    }
}
