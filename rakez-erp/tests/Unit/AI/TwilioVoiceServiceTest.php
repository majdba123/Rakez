<?php

namespace Tests\Unit\AI;

use App\Services\AI\Calling\TwilioVoiceService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TwilioVoiceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ai_calling.twilio.sid', 'ACtest');
        Config::set('ai_calling.twilio.token', 'test-token');
        Config::set('ai_calling.twilio.webhook_base_url', 'https://api.example.com');
        Config::set('ai_calling.call.language', 'ar-SA');
        Config::set('ai_calling.call.voice', 'Polly.Zeina');
        Config::set('ai_calling.call.speech_timeout', 'auto');
        Config::set('ai_calling.call.silence_timeout', 5);
    }

    public function test_hangup_returns_twiml_with_hangup_only(): void
    {
        $service = app(TwilioVoiceService::class);
        $twiml = $service->hangup();
        $xml = (string) $twiml;
        $this->assertStringContainsString('<Response>', $xml);
        $this->assertStringContainsString('<Hangup', $xml);
        $this->assertStringNotContainsString('<Gather', $xml);
        $this->assertStringNotContainsString('<Say', $xml);
    }

    public function test_generate_closing_twiml_includes_say_and_hangup(): void
    {
        $service = app(TwilioVoiceService::class);
        $twiml = $service->generateClosingTwiml('شكراً. مع السلامة.');
        $xml = (string) $twiml;
        $this->assertStringContainsString('<Response>', $xml);
        $this->assertStringContainsString('<Say', $xml);
        $this->assertStringContainsString('شكراً. مع السلامة.', $xml);
        $this->assertStringContainsString('<Hangup', $xml);
    }

    public function test_generate_gather_twiml_includes_gather_say_and_redirect(): void
    {
        $service = app(TwilioVoiceService::class);
        $twiml = $service->generateGatherTwiml('ما اسمك؟', 42, 'q1');
        $xml = (string) $twiml;
        $this->assertStringContainsString('<Response>', $xml);
        $this->assertStringContainsString('<Gather', $xml);
        $this->assertStringContainsString('<Say', $xml);
        $this->assertStringContainsString('ما اسمك؟', $xml);
        $this->assertStringContainsString('/webhooks/twilio/gather/42', $xml);
        $this->assertStringContainsString('qk=q1', $xml);
        $this->assertStringContainsString('/webhooks/twilio/fallback/42', $xml);
        $this->assertStringContainsString('https://api.example.com/api', $xml);
    }

    public function test_generate_say_twiml_includes_say_only(): void
    {
        $service = app(TwilioVoiceService::class);
        $twiml = $service->generateSayTwiml('نص تجريبي');
        $xml = (string) $twiml;
        $this->assertStringContainsString('<Response>', $xml);
        $this->assertStringContainsString('<Say', $xml);
        $this->assertStringContainsString('نص تجريبي', $xml);
        $this->assertStringNotContainsString('<Hangup', $xml);
    }
}
