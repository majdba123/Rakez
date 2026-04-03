<?php

namespace Tests\Integration\AI;

use App\Models\AiAuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;
use Tests\Traits\ReadsDotEnvForTest;
use Tests\Traits\TestsWithRealOpenAiConnection;

#[Group('ai-e2e-real')]
#[Group('ai-qa-hard-proof')]
class AiRealQaToolFailureResilienceTest extends TestCase
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

    /**
     * @return array<string, array{0:string}>
     */
    public static function toolFailureModes(): array
    {
        return [
            'timeout' => ['timeout'],
            'empty' => ['empty'],
            'malformed' => ['malformed'],
            'partial' => ['partial'],
            'unauthorized' => ['unauthorized'],
            'unexpected_schema' => ['unexpected_schema'],
        ];
    }

    #[DataProvider('toolFailureModes')]
    public function test_tool_failure_modes_do_not_leak_and_do_not_fake_success(string $mode): void
    {
        $user = $this->createUserWithBootstrapRole('sales');
        Sanctum::actingAs($user);
        $before = $this->maxToolAuditId($user->id);

        $response = $this->withHeaders([
            'X-AI-QA-Tool-Failure' => "tool_search_records:{$mode}",
        ])->postJson('/api/ai/tools/chat', [
            'message' => 'استخدم tool_search_records وابحث عن lead باسم QA-FAILURE ثم قدم النتائج.',
            'section' => 'general',
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $text = (string) ($data['answer_markdown'] ?? '');
        $confidence = (string) ($data['confidence'] ?? '');

        $this->assertNotSame('', trim($text));
        foreach (['sk-', 'password', 'OPENAI_API_KEY'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $text);
        }

        $toolWasCalled = $this->toolCalledAfter($user->id, $before, 'tool_search_records');
        // Hard-proof: if the failure-injected tool was actually invoked, confidence must not be high.
        if ($toolWasCalled) {
            $this->assertContains($confidence, ['low', 'medium']);
            $auditInput = $this->lastToolCallInputAfter($user->id, $before);
            $this->assertNotNull($auditInput, 'Expected tool_call audit row when tool was invoked');
            $this->assertSame('tool_search_records', $auditInput['tool'] ?? null);
            if ($mode === 'unauthorized') {
                $this->assertTrue((bool) ($auditInput['denied'] ?? false), 'Unauthorized QA injection must set denied=true on audit');
            }
        }
        $this->assertFalse($this->claimsHardSuccess($text), "False success claim detected in mode={$mode}: {$text}");
    }

    public function test_tool_internal_exception_results_in_safe_fallback(): void
    {
        $user = $this->createUserWithBootstrapRole('sales');
        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-AI-QA-Tool-Failure' => 'tool_search_records:exception',
        ])->postJson('/api/ai/tools/chat', [
            'message' => 'استخدم tool_search_records وابحث عن leads ثم قدم النتائج.',
            'section' => 'general',
        ]);

        $response->assertOk();
        $text = (string) $response->json('data.answer_markdown');
        $confidence = (string) $response->json('data.confidence');

        $this->assertNotSame('', trim($text));
        $this->assertContains($confidence, ['low', 'medium']);
        foreach (['sk-', 'password', 'OPENAI_API_KEY'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $text);
        }
    }

    private function claimsHardSuccess(string $text): bool
    {
        $badClaims = [
            'تم تنفيذ بنجاح',
            'تم جلب النتائج الدقيقة',
            'تم العثور على النتائج المؤكدة',
            'نجاح كامل',
        ];
        foreach ($badClaims as $p) {
            if (mb_stripos($text, $p) !== false) {
                return true;
            }
        }

        return false;
    }

    private function maxToolAuditId(int $userId): int
    {
        return (int) (AiAuditEntry::query()
            ->where('user_id', $userId)
            ->where('action', 'tool_call')
            ->max('id') ?? 0);
    }

    private function toolCalledAfter(int $userId, int $beforeId, string $toolName): bool
    {
        return AiAuditEntry::query()
            ->where('user_id', $userId)
            ->where('action', 'tool_call')
            ->where('id', '>', $beforeId)
            ->where('input_summary', 'like', '%"tool":"'.$toolName.'"%')
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastToolCallInputAfter(int $userId, int $beforeId): ?array
    {
        $row = AiAuditEntry::query()
            ->where('user_id', $userId)
            ->where('action', 'tool_call')
            ->where('id', '>', $beforeId)
            ->orderByDesc('id')
            ->first();
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row->input_summary, true);

        return is_array($decoded) ? $decoded : null;
    }
}
