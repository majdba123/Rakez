<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\VoiceChatRequest;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Voice\VoiceAssistantService;
use Illuminate\Http\JsonResponse;

class VoiceAssistantController extends Controller
{
    public function __construct(
        private readonly VoiceAssistantService $voiceAssistantService,
    ) {}

    public function chat(VoiceChatRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->voiceAssistantService->handle($request->user(), $request->validated()),
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
