<?php

namespace Tests\Feature\AI;

use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Feature tests for Twilio webhook endpoints (voice, gather, status, fallback).
 * No real Twilio API calls; signature validation is skipped in testing env.
 */
class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lead $lead;

    private AiCallScript $script;

    private AiCall $call;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai_calling.enabled', true);
        Config::set('ai_calling.twilio.sid', 'ACtest');
        Config::set('ai_calling.twilio.token', 'test-token');
        Config::set('ai_calling.twilio.from_number', '+15551234567');
        Config::set('ai_calling.twilio.webhook_base_url', 'http://test.local');

        $this->user = User::factory()->create();
        $this->lead = Lead::factory()->create([
            'name' => 'Test Lead',
            'contact_info' => json_encode(['phone' => '+966501234567']),
        ]);
        $this->script = AiCallScript::create([
            'name' => 'Test Script',
            'target_type' => 'lead',
            'language' => 'ar',
            'questions' => [
                ['key' => 'q1', 'text_ar' => 'ما اسمك؟', 'text_en' => 'What is your name?'],
            ],
            'greeting_text' => 'السلام عليكم. معاك من شركة راكز.',
            'closing_text' => 'شكراً. مع السلامة.',
            'max_retries_per_question' => 2,
            'is_active' => true,
        ]);
        $this->call = AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Test Lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'pending',
            'initiated_by' => $this->user->id,
        ]);
    }

    public function test_voice_webhook_returns_twiml_and_marks_call_in_progress(): void
    {
        $response = $this->post("/api/webhooks/twilio/voice/{$this->call->id}", [
            'AnsweredBy' => 'human',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
        $this->assertStringContainsString('<Gather', $content);
        $this->assertStringContainsString('<Say', $content);

        $this->call->refresh();
        $this->assertSame('in_progress', $this->call->status);
        $this->assertNotNull($this->call->started_at);
    }

    public function test_voice_webhook_answered_by_machine_returns_hangup_and_marks_failed(): void
    {
        $response = $this->post("/api/webhooks/twilio/voice/{$this->call->id}", [
            'AnsweredBy' => 'machine_start',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
        $this->assertStringContainsString('<Hangup', $content);

        $this->call->refresh();
        $this->assertSame('failed', $this->call->status);
        $this->assertStringContainsString('answered_by_machine', $this->call->call_summary ?? '');
    }

    public function test_voice_webhook_call_not_found_returns_hangup_twiml(): void
    {
        $response = $this->post('/api/webhooks/twilio/voice/99999', [
            'AnsweredBy' => 'human',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('<Hangup', $response->getContent());
    }

    public function test_gather_webhook_with_speech_returns_twiml_and_completes_call_when_one_question(): void
    {
        $this->call->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'current_question_index' => 1,
            'total_questions_asked' => 1,
        ]);

        $response = $this->post("/api/webhooks/twilio/gather/{$this->call->id}?qk=q1", [
            'SpeechResult' => 'أحمد',
            'Confidence' => '0.9',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
        $this->assertStringContainsString('شكراً. مع السلامة.', $content);
        $this->assertStringContainsString('<Hangup', $content);

        $this->call->refresh();
        $this->assertSame('completed', $this->call->status);
    }

    public function test_gather_webhook_with_empty_speech_delegates_to_fallback(): void
    {
        $this->call->update(['status' => 'in_progress', 'started_at' => now()]);

        $response = $this->post("/api/webhooks/twilio/gather/{$this->call->id}?qk=q1", [
            'SpeechResult' => '   ',
            'Confidence' => '0',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');
        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
    }

    public function test_status_webhook_completed_updates_call(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'completed',
            'CallSid' => 'CA1234567890abcdef',
            'CallDuration' => '65',
        ]);

        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('CA1234567890abcdef', $this->call->twilio_call_sid);
        $this->assertSame('completed', $this->call->status);
        $this->assertSame(65, $this->call->duration_seconds);
    }

    public function test_status_webhook_stores_call_sid(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'ringing',
            'CallSid' => 'CAabcdef',
        ]);

        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('CAabcdef', $this->call->twilio_call_sid);
        $this->assertSame('ringing', $this->call->status);
    }

    public function test_status_webhook_no_answer(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'no-answer',
        ]);

        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('no_answer', $this->call->status);
        $this->assertNotNull($this->call->ended_at);
    }

    public function test_fallback_webhook_returns_twiml(): void
    {
        $this->call->update(['status' => 'in_progress', 'started_at' => now()]);

        $response = $this->post("/api/webhooks/twilio/fallback/{$this->call->id}?qk=q1", []);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type') ?? '');
        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
    }

    public function test_fallback_webhook_when_call_finished_returns_hangup(): void
    {
        $this->call->update(['status' => 'completed']);

        $response = $this->post("/api/webhooks/twilio/fallback/{$this->call->id}?qk=q1", []);

        $response->assertStatus(200);
        $this->assertStringContainsString('<Hangup', $response->getContent());
    }

    public function test_gather_webhook_when_call_finished_returns_hangup(): void
    {
        $this->call->update(['status' => 'completed']);

        $response = $this->post("/api/webhooks/twilio/gather/{$this->call->id}?qk=q1", [
            'SpeechResult' => 'نعم',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('<Hangup', $response->getContent());
    }

    public function test_voice_webhook_answered_by_machine_end_beep_marks_failed(): void
    {
        $response = $this->post("/api/webhooks/twilio/voice/{$this->call->id}", [
            'AnsweredBy' => 'machine_end_beep',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('failed', $this->call->status);
        $this->assertStringContainsString('answered_by_machine', $this->call->call_summary ?? '');
    }

    public function test_voice_webhook_answered_by_machine_end_silence_marks_failed(): void
    {
        $response = $this->post("/api/webhooks/twilio/voice/{$this->call->id}", [
            'AnsweredBy' => 'machine_end_silence',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('failed', $this->call->status);
    }

    public function test_voice_webhook_answered_by_fax_marks_failed(): void
    {
        $response = $this->post("/api/webhooks/twilio/voice/{$this->call->id}", [
            'AnsweredBy' => 'fax',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('failed', $this->call->status);
    }

    public function test_status_webhook_busy_updates_call(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'busy',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('busy', $this->call->status);
        $this->assertNotNull($this->call->ended_at);
    }

    public function test_status_webhook_failed_marks_failed(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'failed',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('failed', $this->call->status);
        $this->assertStringContainsString('twilio_failed', $this->call->call_summary ?? '');
    }

    public function test_status_webhook_canceled_updates_call(): void
    {
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'canceled',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('cancelled', $this->call->status);
        $this->assertNotNull($this->call->ended_at);
    }

    public function test_status_webhook_call_not_found_returns_200(): void
    {
        $response = $this->post('/api/webhooks/twilio/status/99999', [
            'CallStatus' => 'completed',
            'CallSid' => 'CAxyz',
        ]);
        $response->assertStatus(200);
        $response->assertSee('OK');
    }

    public function test_gather_webhook_call_not_found_returns_hangup(): void
    {
        $response = $this->post('/api/webhooks/twilio/gather/99999?qk=q1', ['SpeechResult' => 'نعم']);
        $response->assertStatus(200);
        $this->assertStringContainsString('<Hangup', $response->getContent());
    }

    public function test_fallback_webhook_call_not_found_returns_hangup(): void
    {
        $response = $this->post('/api/webhooks/twilio/fallback/99999?qk=q1', []);
        $response->assertStatus(200);
        $this->assertStringContainsString('<Hangup', $response->getContent());
    }

    public function test_status_webhook_in_progress_does_not_change_completed_call(): void
    {
        $this->call->update(['status' => 'completed', 'ended_at' => now(), 'duration_seconds' => 60]);
        $response = $this->post("/api/webhooks/twilio/status/{$this->call->id}", [
            'CallStatus' => 'in-progress',
        ]);
        $response->assertStatus(200);
        $this->call->refresh();
        $this->assertSame('completed', $this->call->status);
        $this->assertSame(60, $this->call->duration_seconds);
    }
}
