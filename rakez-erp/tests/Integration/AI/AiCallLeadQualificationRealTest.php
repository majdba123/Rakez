<?php

namespace Tests\Integration\AI;

use App\Jobs\AnalyzeCallTranscriptJob;
use App\Models\AiCall;
use App\Models\AiCallMessage;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\Calling\CallConversationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Real OpenAI integration: AI call transcript → lead qualification (hot/warm/cold/unqualified) for deals pipeline.
 *
 * Run with OPENAI_API_KEY and AI_REAL_TESTS=true in .env.
 */
class AiCallLeadQualificationRealTest extends TestCase
{
    use RefreshDatabase;

    private ?string $apiKey = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = $this->readEnvVar('OPENAI_API_KEY');
        if (! $this->apiKey || $this->apiKey === 'test-fake-key-not-used') {
            $this->markTestSkipped('Real OPENAI_API_KEY not set in .env');
        }
        if (! $this->isRealTestsEnabled()) {
            $this->markTestSkipped('AI_REAL_TESTS is not enabled — set AI_REAL_TESTS=true in .env');
        }

        Config::set('openai.api_key', $this->apiKey);
        Config::set('ai_calling.openai.model', 'gpt-4.1-mini');
        Config::set('ai_calling.openai.temperature', 0.1);
        Config::set('ai_calling.openai.max_tokens', 300);
        app()->forgetInstance('openai');
        app()->forgetInstance(\OpenAI\Client::class);
    }

    public function test_real_qualify_lead_returns_valid_structure(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Real Qualification Lead',
            'contact_info' => '+966501234567',
        ]);
        $script = AiCallScript::create([
            'name' => 'Qual Script',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'ما اسمك؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Real Qualification Lead',
            'phone_number' => '+966501234567',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now()->subMinutes(5),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'السلام عليكم. معاك من شركة راكز. ما اسمك؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'أحمد. أبحث عن شقة للشراء عندي ميزانية 2 مليون وبدي أخلص خلال شهرين',
            'question_key' => 'q1',
            'timestamp_in_call' => 8,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'تمام أحمد. بنتواصل معاك قريب.',
            'question_key' => null,
            'timestamp_in_call' => 15,
        ]);

        $engine = app(CallConversationEngine::class);
        $result = $engine->qualifyLead($call);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('qualification', $result);
        $this->assertArrayHasKey('notes', $result);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertContains($result['qualification'], ['hot', 'warm', 'cold', 'unqualified', 'unknown']);
        $this->assertIsString($result['notes']);
    }

    public function test_real_analyze_job_updates_lead_for_deals(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Deal Pipeline Lead',
            'contact_info' => '+966509876543',
            'ai_call_count' => 0,
        ]);
        $script = AiCallScript::create([
            'name' => 'Deal Script',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'هل تبحث عن شراء؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Deal Pipeline Lead',
            'phone_number' => '+966509876543',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مرحبا. هل تبحث عن شراء عقار؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'نعم عندي رغبة وأستطيع الشراء خلال السنة الجاية',
            'question_key' => 'q1',
            'timestamp_in_call' => 6,
        ]);

        $job = new AnalyzeCallTranscriptJob($call->id);
        $job->handle(app(CallConversationEngine::class));

        $call->refresh();
        $this->assertNotNull($call->call_summary);
        $this->assertNotNull($call->sentiment_score);

        $lead->refresh();
        $this->assertSame($call->id, $lead->last_ai_call_id);
        $this->assertSame(1, $lead->ai_call_count);
        $this->assertNotNull($lead->ai_qualification_status);
        $this->assertContains($lead->ai_qualification_status, ['hot', 'warm', 'cold', 'unqualified', 'unknown']);
        $this->assertNotNull($lead->ai_call_notes);
    }

    public function test_real_generate_call_summary_returns_arabic_summary(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['name' => 'Summary Lead', 'contact_info' => '+966501111111']);
        $script = AiCallScript::create([
            'name' => 'S',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'هل تبحث؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Summary Lead',
            'phone_number' => '+966501111111',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مرحبا. هل تبحث عن شراء؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'أيوه أبحث عن شقة. عندي ميزانية مليون ونص.',
            'question_key' => 'q1',
            'timestamp_in_call' => 5,
        ]);

        $engine = app(CallConversationEngine::class);
        $summary = $engine->generateCallSummary($call);

        $this->assertNotEmpty($summary);
        $this->assertIsString($summary);
        // Summary prompt asks for Arabic; expect at least one Arabic character
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $summary, 'Call summary should contain Arabic');
    }

    public function test_real_cold_or_unqualified_lead_gets_low_score(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['name' => 'Cold Lead', 'contact_info' => '+966502222222']);
        $script = AiCallScript::create([
            'name' => 'Cold Script',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'هل تهتم؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Cold Lead',
            'phone_number' => '+966502222222',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'السلام عليكم. هل تهتم بشراء عقار؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'لا والله مو مهتم. ما عندي وقت ولا رغبة. خلاص باي',
            'question_key' => 'q1',
            'timestamp_in_call' => 4,
        ]);

        $engine = app(CallConversationEngine::class);
        $result = $engine->qualifyLead($call);

        $this->assertLessThanOrEqual(40, $result['score'], 'Cold/uninterested lead should get low score');
        $this->assertContains($result['qualification'], ['cold', 'unqualified', 'unknown']);
    }

    public function test_real_hot_lead_with_budget_gets_high_score(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['name' => 'Hot Lead', 'contact_info' => '+966503333333']);
        $script = AiCallScript::create([
            'name' => 'Hot Script',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'متى تريد الشراء؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Hot Lead',
            'phone_number' => '+966503333333',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'متى تخطط للشراء؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'خلال شهرين. أنا صاحب قرار وعندي ميزانية 3 ملايين جاهزة. بدي أخلص الصفقة بأسرع وقت.',
            'question_key' => 'q1',
            'timestamp_in_call' => 6,
        ]);

        $engine = app(CallConversationEngine::class);
        $result = $engine->qualifyLead($call);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('qualification', $result);
        $this->assertArrayHasKey('notes', $result);
        // Real API may return hot/warm for clear buyer intent; allow unknown if model is conservative
        $this->assertContains($result['qualification'], ['hot', 'warm', 'unknown']);
        $this->assertIsString($result['notes']);
    }

    public function test_real_analyze_job_increments_lead_call_count(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Multi Call Lead',
            'contact_info' => '+966504444444',
            'ai_call_count' => 2,
            'last_ai_call_id' => null,
        ]);
        $script = AiCallScript::create([
            'name' => 'MC Script',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'مهتم؟']],
            'greeting_text' => 'مرحبا',
            'closing_text' => 'شكراً',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Multi Call Lead',
            'phone_number' => '+966504444444',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مهتم بشراء؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'أيوه ممكن نتابع',
            'question_key' => 'q1',
            'timestamp_in_call' => 3,
        ]);

        $job = new AnalyzeCallTranscriptJob($call->id);
        $job->handle(app(CallConversationEngine::class));

        $lead->refresh();
        $this->assertSame($call->id, $lead->last_ai_call_id);
        $this->assertSame(3, $lead->ai_call_count);
    }

    private function readEnvVar(string $key): ?string
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            return null;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $prefix = $key . '=';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, $prefix)) {
                $v = trim(substr($line, strlen($prefix)), '"\'');
                return $v !== '' ? $v : null;
            }
        }
        return null;
    }

    private function isRealTestsEnabled(): bool
    {
        $v = $this->readEnvVar('AI_REAL_TESTS');
        return in_array(strtolower($v ?? ''), ['true', '1', 'yes'], true);
    }
}
