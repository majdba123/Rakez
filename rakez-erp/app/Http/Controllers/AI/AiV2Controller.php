<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIAssistantService;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\Policy\RakizAiPolicyContextBuilder;
use App\Services\AI\RakizAiOrchestrator;
use App\Services\AI\Skills\SkillRuntime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiV2Controller extends Controller
{
    public function __construct(
        private readonly RakizAiOrchestrator $orchestrator,
        private readonly RakizAiPolicyContextBuilder $policyContext,
        private readonly AIAssistantService $assistantService,
        private readonly SkillRuntime $skillRuntime,
    ) {}

    /**
     * POST /api/ai/tools/chat (preferred). Alias: POST /api/ai/v2/chat.
     * Rakiz orchestrator with strict JSON schema output.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:skill_key|string|max:16000',
            'session_id' => 'nullable|string|max:128',
            'page_context' => 'nullable|array',
            'provider' => 'nullable|string|in:openai,anthropic',
            'skill_key' => 'nullable|string|max:160',
            'skill_input' => 'nullable|array',
        ]);

        $user = $request->user();

        if (! $user->can('use-ai-assistant')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ], 403);
        }

        $this->assistantService->ensureBudgetAvailable($user);

        $skillKey = (string) $request->input('skill_key', '');
        if ($skillKey !== '') {
            $result = $this->skillRuntime->execute(
                $user,
                $skillKey,
                (array) $request->input('skill_input', []),
                [
                    'correlation_id' => $request->header('X-Request-Id')
                        ?? $request->header('X-Request-ID')
                        ?? $request->header('X-Correlation-Id')
                        ?? $request->header('X-Correlation-ID'),
                ],
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);
        $early = $this->policyContext->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
        if ($early !== null) {
            return response()->json([
                'success' => true,
                'data' => $early,
            ]);
        }

        try {
            $result = $this->runOrchestratorWithPolicy($request, $user, $message, $section, $policySnapshot);

            unset($result['_execution_meta']);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (AiAssistantException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }
    }

    /**
     * POST /api/ai/tools/stream (preferred). Alias: POST /api/ai/v2/stream.
     * SSE wrapper (single payload) for clients expecting event-stream.
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'message' => 'required_without:skill_key|string|max:16000',
            'session_id' => 'nullable|string|max:128',
            'page_context' => 'nullable|array',
            'provider' => 'nullable|string|in:openai,anthropic',
            'skill_key' => 'nullable|string|max:160',
            'skill_input' => 'nullable|array',
        ]);

        $user = $request->user();

        // Auth check BEFORE creating StreamedResponse — denied access must return
        // a real HTTP 403, not an SSE error chunk after 200 headers are sent.
        if (! $user->can('use-ai-assistant')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ], 403);
        }

        $this->assistantService->ensureBudgetAvailable($user);

        $skillKey = (string) $request->input('skill_key', '');
        if ($skillKey !== '') {
            return new StreamedResponse(function () use ($request, $user, $skillKey) {
                try {
                    $result = $this->skillRuntime->execute(
                        $user,
                        $skillKey,
                        (array) $request->input('skill_input', []),
                        [
                            'correlation_id' => $request->header('X-Request-Id')
                                ?? $request->header('X-Request-ID')
                                ?? $request->header('X-Correlation-Id')
                                ?? $request->header('X-Correlation-ID'),
                        ],
                    );

                    echo 'data: '.json_encode(['chunk' => $result], JSON_UNESCAPED_UNICODE)."\n\n";
                    echo 'data: '.json_encode(['done' => true])."\n\n";
                    echo "data: [DONE]\n\n";
                } catch (AiAssistantException $e) {
                    echo 'data: '.json_encode([
                        'error' => true,
                        'error_code' => $e->errorCode(),
                        'message' => $e->getMessage(),
                    ])."\n\n";
                    echo "data: [DONE]\n\n";
                } catch (\Throwable) {
                    echo 'data: '.json_encode([
                        'error' => true,
                        'message' => 'An unexpected error occurred.',
                    ])."\n\n";
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

        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);

        return new StreamedResponse(function () use ($request, $user, $message, $section, $policySnapshot) {
            $early = $this->policyContext->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
            if ($early !== null) {
                echo 'data: '.json_encode(['chunk' => $early], JSON_UNESCAPED_UNICODE)."\n\n";
                echo 'data: '.json_encode(['done' => true])."\n\n";
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            try {
                $result = $this->runOrchestratorWithPolicy($request, $user, $message, $section, $policySnapshot);
                unset($result['_execution_meta']);

                echo 'data: '.json_encode(['chunk' => $result], JSON_UNESCAPED_UNICODE)."\n\n";
                echo 'data: '.json_encode(['done' => true])."\n\n";
                echo "data: [DONE]\n\n";
            } catch (AiAssistantException $e) {
                echo 'data: '.json_encode([
                    'error' => true,
                    'error_code' => $e->errorCode(),
                    'message' => $e->getMessage(),
                ])."\n\n";
                echo "data: [DONE]\n\n";
            } catch (\Throwable) {
                echo 'data: '.json_encode([
                    'error' => true,
                    'message' => 'An unexpected error occurred.',
                ])."\n\n";
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
    private function preparePolicyContext(Request $request, $user): array
    {
        $message = (string) $request->input('message');
        $section = (string) $request->input('section', 'general');
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
    private function runOrchestratorWithPolicy(Request $request, $user, string $message, string $section, array $policySnapshot): array
    {
        $result = $this->orchestrator->chat(
            $user,
            $message,
            $request->input('session_id'),
            array_merge(
                (array) $request->input('page_context', []),
                [
                    'section' => $section,
                    'policy_snapshot' => $policySnapshot,
                    'provider' => $request->input('provider'),
                ]
            ),
        );

        return $this->policyContext->applySnapshotNormalization($result, $policySnapshot);
    }
}
