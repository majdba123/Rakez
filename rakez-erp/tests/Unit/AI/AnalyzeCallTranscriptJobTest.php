<?php

namespace Tests\Unit\AI;

use App\Jobs\AnalyzeCallTranscriptJob;
use App\Models\AiCall;
use App\Models\AiCallMessage;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\Calling\CallConversationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for AnalyzeCallTranscriptJob: call summary, lead qualification, and lead record updates (path to deals).
 */
class AnalyzeCallTranscriptJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_updates_call_summary_and_sentiment_and_lead_qualification(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Deal Lead',
            'contact_info' => '+966501234567',
            'ai_call_count' => 0,
        ]);
        $script = AiCallScript::create([
            'name' => 'S',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'Q']],
            'greeting_text' => 'Hi',
            'closing_text' => 'Bye',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
            'started_at' => now(),
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مرحبا ما اسمك؟',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'client',
            'content' => 'أحمد. عندي ميزانية وأريد شراء خلال شهرين',
            'question_key' => 'q1',
            'timestamp_in_call' => 5,
        ]);

        $engine = Mockery::mock(CallConversationEngine::class);
        $engine->shouldReceive('generateCallSummary')
            ->once()
            ->with(Mockery::type(AiCall::class))
            ->andReturn('عميل مهتم، عنده ميزانية ويريد شراء قريباً.');
        $engine->shouldReceive('qualifyLead')
            ->once()
            ->with(Mockery::type(AiCall::class))
            ->andReturn([
                'score' => 85.0,
                'qualification' => 'hot',
                'notes' => 'جاهز للشراء خلال 3 شهور، صاحب قرار.',
            ]);
        $this->instance(CallConversationEngine::class, $engine);

        $job = new AnalyzeCallTranscriptJob($call->id);
        $job->handle(app(CallConversationEngine::class));

        $call->refresh();
        $this->assertSame('عميل مهتم، عنده ميزانية ويريد شراء قريباً.', $call->call_summary);
        $this->assertEqualsWithDelta(0.85, (float) $call->sentiment_score, 0.01);

        $lead->refresh();
        $this->assertSame($call->id, $lead->last_ai_call_id);
        $this->assertSame(1, $lead->ai_call_count);
        $this->assertSame('hot', $lead->ai_qualification_status);
        $this->assertSame('جاهز للشراء خلال 3 شهور، صاحب قرار.', $lead->ai_call_notes);
    }

    public function test_handle_exits_when_call_not_found(): void
    {
        $engine = Mockery::mock(CallConversationEngine::class);
        $engine->shouldNotReceive('generateCallSummary');
        $engine->shouldNotReceive('qualifyLead');
        $this->instance(CallConversationEngine::class, $engine);

        $job = new AnalyzeCallTranscriptJob(99999);
        $job->handle(app(CallConversationEngine::class));

        $this->assertNull(AiCall::find(99999));
    }

    public function test_handle_exits_when_no_messages(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create(['contact_info' => '+966501234567']);
        $script = AiCallScript::create([
            'name' => 'S',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'Q']],
            'greeting_text' => 'Hi',
            'closing_text' => 'Bye',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
        ]);

        $engine = Mockery::mock(CallConversationEngine::class);
        $engine->shouldNotReceive('generateCallSummary');
        $engine->shouldNotReceive('qualifyLead');
        $this->instance(CallConversationEngine::class, $engine);

        $job = new AnalyzeCallTranscriptJob($call->id);
        $job->handle(app(CallConversationEngine::class));

        $lead->refresh();
        $this->assertNull($lead->ai_qualification_status);
    }

    public function test_handle_does_not_update_lead_when_no_lead_id(): void
    {
        $user = User::factory()->create();
        $script = AiCallScript::create([
            'name' => 'S',
            'target_type' => 'lead',
            'questions' => [['key' => 'q1', 'text_ar' => 'Q']],
            'greeting_text' => 'Hi',
            'closing_text' => 'Bye',
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => null,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $script->id,
            'status' => 'completed',
            'initiated_by' => $user->id,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مرحبا',
            'question_key' => null,
            'timestamp_in_call' => 0,
        ]);

        $engine = Mockery::mock(CallConversationEngine::class);
        $engine->shouldReceive('generateCallSummary')->once()->andReturn('ملخص');
        $engine->shouldReceive('qualifyLead')->once()->andReturn([
            'score' => 50.0,
            'qualification' => 'warm',
            'notes' => 'ملاحظات',
        ]);
        $this->instance(CallConversationEngine::class, $engine);

        $job = new AnalyzeCallTranscriptJob($call->id);
        $job->handle(app(CallConversationEngine::class));

        $call->refresh();
        $this->assertSame('ملخص', $call->call_summary);
        $this->assertNull($call->lead_id);
    }
}
