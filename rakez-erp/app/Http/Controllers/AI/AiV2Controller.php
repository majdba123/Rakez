<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\CanonicalToolChatRequest;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Http\CanonicalAiRequestContext;
use App\Services\AI\Http\CanonicalAiResponse;
use App\Services\AI\Policy\RakizAiPolicyContextBuilder;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiV2Controller extends Controller
{
    private const USE_AI_PERMISSION = 'use-ai-assistant';

    private const FORBIDDEN_MESSAGE = 'You do not have permission to use the AI assistant.';

    public function __construct(
        private readonly RakizAiOrchestrator $orchestrator,
        private readonly RakizAiPolicyContextBuilder $policyContext,
        private readonly CanonicalAiRequestContext $requestContext,
        private readonly CanonicalAiResponse $responseFormatter,
    ) {}

    /**
     * POST /api/ai/tools/chat (preferred). Alias: POST /api/ai/v2/chat.
     * Rakiz orchestrator with strict JSON schema output.
     */
    public function chat(CanonicalToolChatRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->can(self::USE_AI_PERMISSION)) {
            return $this->responseFormatter->errorJson(
                'forbidden',
                self::FORBIDDEN_MESSAGE,
                403
            );
        }

        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);
        $early = $this->policyContext->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
        if ($early !== null) {
            return response()->json($this->successEnvelope($request, $early));
        }

        try {
            $result = $this->runOrchestratorWithPolicy($request, $user, $message, $section, $policySnapshot);
            $executionMeta = (array) ($result['_execution_meta'] ?? []);

            unset($result['_execution_meta']);

            return response()->json($this->successEnvelope($request, $result, $executionMeta));
        } catch (AiAssistantException $e) {
            return $this->responseFormatter->errorJson($e->errorCode(), $e->getMessage(), $e->statusCode());
        }
    }

    /**
     * POST /api/ai/tools/stream (preferred). Alias: POST /api/ai/v2/stream.
     * SSE wrapper (single payload) for clients expecting event-stream.
     */
    public function stream(CanonicalToolChatRequest $request): StreamedResponse
    {
        $user = $request->user();
        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);

        return new StreamedResponse(function () use ($request, $user, $message, $section, $policySnapshot) {
            if (! $user->can(self::USE_AI_PERMISSION)) {
                echo $this->sseData($this->responseFormatter->errorEnvelope(
                    'forbidden',
                    self::FORBIDDEN_MESSAGE
                ));
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            $early = $this->policyContext->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
            if ($early !== null) {
                echo $this->sseData($this->successEnvelope($request, $early));
                echo 'data: '.json_encode(['done' => true])."\n\n";
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            try {
                $result = $this->runOrchestratorWithPolicy($request, $user, $message, $section, $policySnapshot);
                $executionMeta = (array) ($result['_execution_meta'] ?? []);
                unset($result['_execution_meta']);

                echo $this->sseData($this->successEnvelope($request, $result, $executionMeta));
                echo 'data: '.json_encode(['done' => true])."\n\n";
                echo "data: [DONE]\n\n";
            } catch (AiAssistantException $e) {
                echo $this->sseData($this->responseFormatter->errorEnvelope($e->errorCode(), $e->getMessage()));
                echo "data: [DONE]\n\n";
            } catch (\Throwable) {
                echo $this->sseData($this->responseFormatter->errorEnvelope(
                    'server_error',
                    'An unexpected error occurred.'
                ));
                echo "data: [DONE]\n\n";
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array{message:string, section:string, policy_snapshot:array<string,mixed>}
     */
    private function preparePolicyContext(CanonicalToolChatRequest $request, $user): array
    {
        $message = $this->requestContext->message($request);
        $section = $this->requestContext->section($request);
        $policySnapshot = $this->policyContext->buildDeterministicPolicySnapshot($user, $message, $section);

        return [
            'message' => $message,
            'section' => $section,
            'policy_snapshot' => $policySnapshot,
        ];
    }

    /**
     * @param  array<string, mixed>  $policySnapshot
     * @return array<string, mixed>
     */
    private function runOrchestratorWithPolicy(CanonicalToolChatRequest $request, $user, string $message, string $section, array $policySnapshot): array
    {
        $conversationId = $this->requestContext->conversationId($request);
        $context = $this->requestContext->erpContext($request);

        $result = $this->orchestrator->chat(
            $user,
            $message,
            $conversationId,
            array_merge(
                $context,
                [
                    'section' => $section,
                    'policy_snapshot' => $policySnapshot,
                ]
            ),
        );

        return $this->policyContext->applySnapshotNormalization($result, $policySnapshot);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $executionMeta
     * @return array<string, mixed>
     */
    private function successEnvelope(CanonicalToolChatRequest $request, array $result, array $executionMeta = []): array
    {
        return $this->responseFormatter->successEnvelope(
            $result,
            $executionMeta,
            $this->requestContext->conversationId($request),
            $this->requestContext->locale($request),
            $this->requestContext->inputMode($request)
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sseData(array $payload): string
    {
        return 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
