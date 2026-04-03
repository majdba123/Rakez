<?php

namespace App\Services\AI\Policy;

use App\Models\User;

/**
 * Deterministic policy snapshot + pre-LLM gates shared by AiV2Controller and AIAssistantService hybrid orchestrator path.
 */
class RakizAiPolicyContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function buildDeterministicPolicySnapshot(User $user, string $message, string $section): array
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
    public function earlyPolicyGateResponse(User $user, string $message, string $section, array $snapshot): ?array
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
    public function applySnapshotNormalization(array $result, array $snapshot): array
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
