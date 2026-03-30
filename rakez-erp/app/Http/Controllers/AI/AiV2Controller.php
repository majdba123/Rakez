<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\Exceptions\AiAssistantException;
use App\Services\AI\RakizAiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiV2Controller extends Controller
{
    public function __construct(
        private readonly RakizAiOrchestrator $orchestrator,
    ) {}

    /**
     * POST /api/ai/tools/chat (preferred). Alias: POST /api/ai/v2/chat.
     * Rakiz orchestrator with strict JSON schema output.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'session_id' => 'nullable|string|max:128',
            'page_context' => 'nullable|array',
        ]);

        $user = $request->user();

        if (! $user->can('use-ai-assistant')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ], 403);
        }

        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);
        $early = $this->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
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
    public function stream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:16000',
            'session_id' => 'nullable|string|max:128',
            'page_context' => 'nullable|array',
        ]);

        $user = $request->user();
        ['message' => $message, 'section' => $section, 'policy_snapshot' => $policySnapshot] = $this->preparePolicyContext($request, $user);

        return new StreamedResponse(function () use ($request, $user, $message, $section, $policySnapshot) {
            if (! $user->can('use-ai-assistant')) {
                echo 'data: ' . json_encode(['error' => true, 'message' => 'Forbidden']) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            $early = $this->earlyPolicyGateResponse($user, $message, $section, $policySnapshot);
            if ($early !== null) {
                echo 'data: ' . json_encode(['chunk' => $early], JSON_UNESCAPED_UNICODE) . "\n\n";
                echo 'data: ' . json_encode(['done' => true]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();

                return;
            }

            try {
                $result = $this->runOrchestratorWithPolicy($request, $user, $message, $section, $policySnapshot);
                unset($result['_execution_meta']);

                echo 'data: ' . json_encode(['chunk' => $result], JSON_UNESCAPED_UNICODE) . "\n\n";
                echo 'data: ' . json_encode(['done' => true]) . "\n\n";
                echo "data: [DONE]\n\n";
            } catch (\Throwable $e) {
                echo 'data: ' . json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                ]) . "\n\n";
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
        $policySnapshot = $this->buildDeterministicPolicySnapshot($user, $message, $section);

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
                ]
            ),
        );

        return $this->applySnapshotNormalization($result, $policySnapshot);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDeterministicPolicySnapshot($user, string $message, string $section): array
    {
        $normalized = mb_strtolower(trim($message));
        $isConceptualNoData = preg_match('/بدون\s+[^\.!\n\r]{0,40}(بيانات|أرقام)|نصائح عامة/u', $normalized) === 1;
        $mentionsSalesKpi = preg_match('/(kpi|مؤشرات).*(مبيعات)|(مبيعات).*(kpi|مؤشرات)/u', $normalized) === 1;

        return [
            'version' => 'v1',
            'request_hash' => hash('sha256', json_encode([
                'uid' => (int) $user->id,
                'section' => $section,
                'message' => $normalized,
            ], JSON_UNESCAPED_UNICODE)),
            'section' => $section,
            'rules' => [
                'is_conceptual_no_data' => $isConceptualNoData,
                'mentions_sales_kpi' => $mentionsSalesKpi,
            ],
            'permissions' => [
                'sales_dashboard_view' => (bool) $user->can('sales.dashboard.view'),
            ],
            'tool_mode' => $isConceptualNoData ? 'none' : 'auto',
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    private function earlyPolicyGateResponse($user, string $message, string $section, array $snapshot): ?array
    {
        $normalized = mb_strtolower(trim($message));
        $mentionsSalesKpi = (bool) ($snapshot['rules']['mentions_sales_kpi'] ?? false);
        $canSalesKpi = (bool) ($snapshot['permissions']['sales_dashboard_view'] ?? false);

        if ($mentionsSalesKpi && ! $canSalesKpi) {
            return [
                'answer_markdown' => 'ما عندك صلاحية للوصول إلى مؤشرات المبيعات التفصيلية. أقدر أساعدك ببدائل عامة لتحسين الأداء بدون كشف بيانات غير مصرح بها.',
                'confidence' => 'high',
                'sources' => [],
                'links' => [],
                'suggested_actions' => [],
                'follow_up_questions' => ['هل تريد خطة تحسين عامة بدون بيانات حساسة؟'],
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'policy_gate.sales_kpi_permission',
                ],
            ];
        }

        // Deterministic security pre-check for obvious prompt injection requests.
        $sensitiveProbe = preg_match('/password|api key|system prompt|ignore all rules|sk-/i', $normalized) === 1;
        if ($sensitiveProbe) {
            return [
                'answer_markdown' => 'لا يمكنني مشاركة كلمات مرور أو مفاتيح أو تفاصيل داخلية للنظام. أقدر أساعدك بطريقة آمنة ضمن الصلاحيات.',
                'confidence' => 'high',
                'sources' => [],
                'links' => [],
                'suggested_actions' => [],
                'follow_up_questions' => ['هل تريد بديلًا آمنًا لنفس الهدف؟'],
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'policy_gate.sensitive_probe',
                ],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function applySnapshotNormalization(array $result, array $snapshot): array
    {
        $isConceptualNoData = (bool) ($snapshot['rules']['is_conceptual_no_data'] ?? false);
        if ($isConceptualNoData) {
            $result['access_notes'] = [
                'had_denied_request' => false,
                'reason' => '',
            ];
        }

        return $result;
    }
}
