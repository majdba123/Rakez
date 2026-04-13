<?php

namespace Tests\Unit\AI;

use App\Services\AI\Exceptions\AiBudgetExceededException;
use Tests\TestCase;

class AiAssistantMessagesTest extends TestCase
{
    public function test_budget_exception_message_is_arabic_from_config(): void
    {
        $e = new AiBudgetExceededException(1000, 1200);

        $this->assertStringContainsString('1200', $e->getMessage());
        $this->assertStringContainsString('1000', $e->getMessage());
        $this->assertStringContainsString('تجاوز', $e->getMessage());
    }

    public function test_orchestrator_message_keys_exist_in_config(): void
    {
        $orchestrator = config('ai_assistant.messages.orchestrator', []);

        foreach (['could_not_complete', 'parse_failed', 'tool_limit', 'generic_error'] as $key) {
            $this->assertArrayHasKey($key, $orchestrator);
            $this->assertNotSame('', trim((string) $orchestrator[$key]));
        }

        $this->assertNotEmpty(trim((string) config('ai_assistant.messages.empty_response')));
    }

    public function test_empty_response_message_is_arabic(): void
    {
        $this->assertStringContainsString('تعذ', (string) config('ai_assistant.messages.empty_response'));
    }
}
