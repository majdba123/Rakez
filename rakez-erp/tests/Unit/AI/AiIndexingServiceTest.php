<?php

namespace Tests\Unit\AI;

use App\Services\AI\AiIndexingService;
use PHPUnit\Framework\TestCase;

class AiIndexingServiceTest extends TestCase
{
    private AiIndexingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiIndexingService;
    }

    public function test_redacts_openai_api_keys(): void
    {
        $text = 'API key is sk-abc123def456ghi789jkl012mno345';
        $result = $this->service->redactSecrets($text);

        $this->assertStringNotContainsString('sk-abc123', $result);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $result);
    }

    public function test_redacts_bearer_tokens(): void
    {
        $text = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test';
        $result = $this->service->redactSecrets($text);

        $this->assertStringContainsString('[REDACTED_TOKEN]', $result);
    }

    public function test_redacts_saudi_national_ids(): void
    {
        $text = 'رقم الهوية: 1098765432';
        $result = $this->service->redactSecrets($text);

        $this->assertStringNotContainsString('1098765432', $result);
        $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $result);
    }

    public function test_redacts_saudi_ibans(): void
    {
        $text = 'IBAN: SA0380000000608010167519';
        $result = $this->service->redactSecrets($text);

        $this->assertStringNotContainsString('SA0380000000608010167519', $result);
        $this->assertStringContainsString('[REDACTED_IBAN]', $result);
    }

    public function test_redacts_database_urls(): void
    {
        $text = 'Connection: mysql://user:pass@localhost/db';
        $result = $this->service->redactSecrets($text);

        $this->assertStringContainsString('[REDACTED_DB_URL]', $result);
    }

    public function test_returns_empty_for_empty_input(): void
    {
        $this->assertEquals('', $this->service->redactSecrets(''));
    }

    public function test_preserves_normal_text(): void
    {
        $text = 'This is a normal message without any secrets.';
        $this->assertEquals($text, $this->service->redactSecrets($text));
    }
}
