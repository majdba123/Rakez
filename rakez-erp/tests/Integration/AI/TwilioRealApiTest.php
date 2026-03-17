<?php

namespace Tests\Integration\AI;

use App\Models\AiCall;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\Calling\TwilioVoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Api\V2010\Account\BalanceInstance;
use Twilio\Rest\Client as TwilioClient;

/**
 * Real Twilio API integration tests. Require valid credentials in .env and TWILIO_REAL_TESTS=true.
 *
 * To run:
 *   1. In .env set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER
 *   2. In .env set TWILIO_REAL_TESTS=true
 *   3. Run: php artisan test tests/Integration/AI/TwilioRealApiTest.php
 */
class TwilioRealApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $sid = null;

    private ?string $token = null;

    private ?string $phoneNumber = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sid = $this->readEnvVar('TWILIO_ACCOUNT_SID');
        $this->token = $this->readEnvVar('TWILIO_AUTH_TOKEN');
        $this->phoneNumber = $this->readEnvVar('TWILIO_PHONE_NUMBER');

        if (! $this->sid || ! $this->token) {
            $this->markTestSkipped('TWILIO_ACCOUNT_SID and TWILIO_AUTH_TOKEN must be set in .env');
        }

        if (! $this->isRealTestsEnabled()) {
            $this->markTestSkipped('TWILIO_REAL_TESTS is not enabled — set TWILIO_REAL_TESTS=true in .env to run');
        }

        Config::set('ai_calling.enabled', true);
        Config::set('ai_calling.twilio.sid', $this->sid);
        Config::set('ai_calling.twilio.token', $this->token);
        Config::set('ai_calling.twilio.from_number', $this->phoneNumber ?? '');
        Config::set('ai_calling.twilio.webhook_base_url', 'http://test.local');
    }

    public function test_twilio_credentials_connectivity(): void
    {
        $client = new TwilioClient($this->sid, $this->token);

        $balance = $client->api->v2010->account->balance->fetch();

        $this->assertInstanceOf(BalanceInstance::class, $balance);
        $this->assertNotNull($balance->accountSid);
    }

    /**
     * Optional: initiates a real outbound call to TWILIO_PHONE_NUMBER (or a verified number in Trial).
     * Skip if TWILIO_PHONE_NUMBER is not set. May place a real call when run.
     */
    public function test_initiate_call_returns_call_sid_when_configured(): void
    {
        if (! $this->phoneNumber) {
            $this->markTestSkipped('TWILIO_PHONE_NUMBER not set in .env — cannot initiate call');
        }

        $user = User::factory()->create();
        $lead = Lead::factory()->create([
            'name' => 'Twilio Test Lead',
            'contact_info' => json_encode(['phone' => $this->phoneNumber]),
        ]);
        $script = AiCallScript::create([
            'name' => 'Test Script',
            'target_type' => 'lead',
            'language' => 'ar',
            'questions' => [
                ['key' => 'q1', 'text_ar' => 'اختبار', 'text_en' => 'Test'],
            ],
            'greeting_text' => 'مرحبا. هذه مكالمة اختبار.',
            'closing_text' => 'شكراً.',
            'max_retries_per_question' => 1,
            'is_active' => true,
        ]);
        $call = AiCall::create([
            'lead_id' => $lead->id,
            'customer_type' => 'lead',
            'phone_number' => $this->phoneNumber,
            'script_id' => $script->id,
            'status' => 'pending',
            'initiated_by' => $user->id,
        ]);

        $twilioService = app(TwilioVoiceService::class);

        try {
            $callSid = $twilioService->initiateCall($this->phoneNumber, $call->id);
        } catch (RestException $e) {
            if (str_contains($e->getMessage(), 'not yet verified') || str_contains($e->getMessage(), 'not verified')) {
                $this->markTestSkipped('Twilio Trial: FROM number must be verified or purchased. Skipping initiate-call test.');
            }
            throw $e;
        }

        $this->assertNotEmpty($callSid);
        $this->assertStringStartsWith('CA', $callSid);
    }

    private function readEnvVar(string $key): ?string
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return null;
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $prefix = $key . '=';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, $prefix)) {
                $value = trim(substr($line, strlen($prefix)), '"\'');
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    private function isRealTestsEnabled(): bool
    {
        $value = $this->readEnvVar('TWILIO_REAL_TESTS');
        return in_array(strtolower($value ?? ''), ['true', '1', 'yes'], true);
    }
}
