<?php

namespace Tests\Integration\AI;

use App\Models\AiAuditEntry;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AiHardProofJudge;
use Tests\Support\AiStrictScenarioEvaluator;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;
use Tests\Traits\ReadsDotEnvForTest;
use Tests\Traits\TestsWithRealOpenAiConnection;

#[Group('ai-e2e-real')]
#[Group('ai-qa-hard-proof')]
class AiRealQaHardProofToolsDecisionTest extends TestCase
{
    use CreatesUsersWithBootstrapRole;
    use ReadsDotEnvForTest;
    use RefreshDatabase;
    use TestsWithRealOpenAiConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealOpenAiFromDotEnv();
    }

    public function test_tool_decision_must_call_search_records_is_proven_with_trace(): void
    {
        $user = $this->createUserWithBootstrapRole('sales');
        Sanctum::actingAs($user);

        $token = 'HP-LEAD-'.uniqid();
        Lead::factory()->create([
            'name' => $token,
            'assigned_to' => $user->id,
        ]);

        $before = $this->maxToolAuditId($user->id);
        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => "استخدم tool_search_records فقط وابحث عن leads بالاسم {$token} ثم لخص النتيجة.",
            'section' => 'general',
        ]);
        $response->assertOk();
        $data = $response->json('data');

        $calls = $this->toolAuditDelta($user->id, $before);
        $scenario = [
            'expected_status' => 200,
            'expected_tool_decision' => 'must_call',
            'expected_tool_name' => 'tool_search_records',
            'required_facts' => ['lead', 'ليد'],
            'forbidden_facts' => ['sk-', 'password', 'OPENAI_API_KEY'],
            'min_required_facts_hit' => 0,
            'min_quality_threshold' => 70,
        ];
        $actual = [
            'http_status' => $response->status(),
            'text' => (string) ($data['answer_markdown'] ?? ''),
            'tool_calls' => $calls,
        ];
        $eval = AiStrictScenarioEvaluator::evaluate($scenario, $actual);
        $proof = [
            'decision_evidence' => in_array('tool_search_records', $calls, true),
            'trace_evidence' => count($calls) > 0,
        ];

        $this->assertTrue(AiHardProofJudge::hardProofPass($eval, $proof), json_encode([$eval, $calls], JSON_UNESCAPED_UNICODE));
    }

    public function test_tool_decision_must_not_call_for_conceptual_query_is_proven(): void
    {
        $user = $this->createUserWithBootstrapRole('sales');
        Sanctum::actingAs($user);

        $before = $this->maxToolAuditId($user->id);
        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'اعطني 5 نصائح عامة لإدارة الوقت في العمل بدون استخدام بيانات النظام.',
            'section' => 'general',
        ]);
        $response->assertOk();
        $data = $response->json('data');

        $calls = $this->toolAuditDelta($user->id, $before);
        $scenario = [
            'expected_status' => 200,
            'expected_tool_decision' => 'must_not_call',
            'required_facts' => ['نصائح'],
            'forbidden_facts' => ['tool_', 'sk-', 'password'],
            'min_required_facts_hit' => 0,
            'min_quality_threshold' => 65,
        ];
        $actual = [
            'http_status' => $response->status(),
            'text' => (string) ($data['answer_markdown'] ?? ''),
            'tool_calls' => $calls,
        ];
        $eval = AiStrictScenarioEvaluator::evaluate($scenario, $actual);
        $proof = [
            'decision_evidence' => count($calls) === 0,
            'trace_evidence' => true,
        ];

        $this->assertTrue(AiHardProofJudge::hardProofPass($eval, $proof), json_encode([$eval, $calls], JSON_UNESCAPED_UNICODE));
    }

    public function test_tool_decision_forbidden_tool_is_proven_by_absence_or_denial_trace(): void
    {
        $user = $this->createUserWithBootstrapRole('marketing');
        Sanctum::actingAs($user);

        $before = $this->maxToolAuditId($user->id);
        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'اعطني KPI مبيعات دقيقة جدًا بالأرقام النهائية لكل فريق اليوم.',
            'section' => 'general',
        ]);
        $response->assertOk();
        $data = $response->json('data');

        $calls = $this->toolAuditDelta($user->id, $before);
        $usedForbidden = in_array('tool_kpi_sales', $calls, true);
        $deniedFlag = (bool) ($data['access_notes']['had_denied_request'] ?? false);

        $scenario = [
            'expected_status' => 200,
            'expected_tool_decision' => 'must_not_call_expected',
            'expected_tool_name' => 'tool_kpi_sales',
            'required_facts' => [],
            'forbidden_facts' => ['sk-', 'password'],
            'min_required_facts_hit' => 0,
            'min_quality_threshold' => 60,
        ];
        $actual = [
            'http_status' => $response->status(),
            'text' => (string) ($data['answer_markdown'] ?? ''),
            'tool_calls' => $calls,
        ];
        $eval = AiStrictScenarioEvaluator::evaluate($scenario, $actual);
        $proof = [
            'decision_evidence' => (! $usedForbidden) || $deniedFlag,
            'trace_evidence' => true,
        ];

        $this->assertTrue(AiHardProofJudge::hardProofPass($eval, $proof), json_encode([$eval, $calls, $data], JSON_UNESCAPED_UNICODE));
    }

    private function maxToolAuditId(int $userId): int
    {
        return (int) (AiAuditEntry::query()
            ->where('user_id', $userId)
            ->where('action', 'tool_call')
            ->max('id') ?? 0);
    }

    /**
     * @return array<int, string>
     */
    private function toolAuditDelta(int $userId, int $beforeId): array
    {
        return AiAuditEntry::query()
            ->where('user_id', $userId)
            ->where('action', 'tool_call')
            ->where('id', '>', $beforeId)
            ->orderBy('id')
            ->get()
            ->map(function (AiAuditEntry $e) {
                $input = (string) $e->input_summary;
                if (preg_match('/"tool"\s*:\s*"([^"]+)"/', $input, $m) === 1) {
                    return (string) $m[1];
                }

                return '';
            })
            ->filter()
            ->values()
            ->all();
    }
}

