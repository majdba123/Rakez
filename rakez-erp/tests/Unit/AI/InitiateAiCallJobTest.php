<?php

namespace Tests\Unit\AI;

use App\Jobs\InitiateAiCallJob;
use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\Calling\TwilioVoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class InitiateAiCallJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai_calling.enabled', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_exits_when_call_not_found(): void
    {
        $twilio = Mockery::mock(TwilioVoiceService::class);
        $twilio->shouldNotReceive('initiateCall');
        $this->instance(TwilioVoiceService::class, $twilio);

        $job = new InitiateAiCallJob(99999);
        $job->handle(app(TwilioVoiceService::class));

        $this->assertNull(AiCall::find(99999));
    }

    public function test_handle_exits_when_call_not_pending(): void
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

        $twilio = Mockery::mock(TwilioVoiceService::class);
        $twilio->shouldNotReceive('initiateCall');
        $this->instance(TwilioVoiceService::class, $twilio);

        $job = new InitiateAiCallJob($call->id);
        $job->handle(app(TwilioVoiceService::class));

        $call->refresh();
        $this->assertSame('completed', $call->status);
    }

    public function test_handle_marks_failed_when_ai_calling_disabled(): void
    {
        Config::set('ai_calling.enabled', false);
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
            'status' => 'pending',
            'initiated_by' => $user->id,
        ]);

        $twilio = Mockery::mock(TwilioVoiceService::class);
        $twilio->shouldNotReceive('initiateCall');
        $this->instance(TwilioVoiceService::class, $twilio);

        $job = new InitiateAiCallJob($call->id);
        $job->handle(app(TwilioVoiceService::class));

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertStringContainsString('ai_calling_disabled', $call->call_summary ?? '');
    }

    public function test_handle_calls_twilio_and_marks_ringing_on_success(): void
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
            'status' => 'pending',
            'initiated_by' => $user->id,
        ]);

        $twilio = Mockery::mock(TwilioVoiceService::class);
        $twilio->shouldReceive('initiateCall')
            ->once()
            ->with('+966501234567', $call->id)
            ->andReturn('CA123456');
        $this->instance(TwilioVoiceService::class, $twilio);

        $job = new InitiateAiCallJob($call->id);
        $job->handle(app(TwilioVoiceService::class));

        $call->refresh();
        $this->assertSame('ringing', $call->status);
        $this->assertSame('CA123456', $call->twilio_call_sid);
    }

    public function test_handle_marks_failed_when_twilio_throws(): void
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
            'status' => 'pending',
            'initiated_by' => $user->id,
        ]);

        $twilio = Mockery::mock(TwilioVoiceService::class);
        $twilio->shouldReceive('initiateCall')
            ->once()
            ->andThrow(new \Exception('Twilio error'));
        $this->instance(TwilioVoiceService::class, $twilio);

        $job = new InitiateAiCallJob($call->id);
        $job->handle(app(TwilioVoiceService::class));

        $call->refresh();
        $this->assertSame('failed', $call->status);
        $this->assertStringContainsString('Twilio error', $call->call_summary ?? '');
    }
}
