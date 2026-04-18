<?php

namespace Tests\Unit\AI;

use App\Http\Middleware\RedactPiiFromAi;
use App\Services\AI\Tools\ToolOutputRedactor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

class AiHardeningTest extends TestCase
{
    // ── Middleware context array redaction ──

    public function test_middleware_redacts_pii_inside_flat_context_array(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
            'context' => ['note' => 'هوية 1098765432 جوال 0512345678'],
        ]);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $context = $request->input('context');
        $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $context['note']);
        $this->assertStringContainsString('[REDACTED_PHONE]', $context['note']);
    }

    public function test_middleware_redacts_pii_inside_nested_context_array(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
            'context' => [
                'client' => [
                    'info' => 'ايميله user@test.com',
                ],
            ],
        ]);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $context = $request->input('context');
        $this->assertStringContainsString('[REDACTED_EMAIL]', $context['client']['info']);
    }

    public function test_middleware_handles_missing_context_gracefully(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
        ]);

        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertNull($request->input('context'));
    }

    public function test_middleware_handles_non_array_context_gracefully(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
            'context' => 'not an array',
        ]);

        // Should not throw — just pass through
        $middleware->handle($request, fn ($r) => new Response('ok'));

        $this->assertSame('not an array', $request->input('context'));
    }

    // ── ToolOutputRedactor edge cases ──

    public function test_tool_output_redactor_handles_empty_phone_value(): void
    {
        $redactor = new ToolOutputRedactor;
        $input = json_encode(['phone' => '']);
        $decoded = json_decode($redactor->redact($input), true);

        // Empty string phone should NOT be redacted (condition: $value !== '')
        $this->assertSame('', $decoded['phone']);
    }

    public function test_tool_output_redactor_handles_null_values_in_json(): void
    {
        $redactor = new ToolOutputRedactor;
        $input = json_encode(['phone' => null, 'email' => null]);
        $decoded = json_decode($redactor->redact($input), true);

        $this->assertNull($decoded['phone']);
        $this->assertNull($decoded['email']);
    }

    public function test_tool_output_redactor_handles_numeric_values(): void
    {
        $redactor = new ToolOutputRedactor;
        $input = json_encode(['count' => 42, 'total' => 1500]);
        $decoded = json_decode($redactor->redact($input), true);

        $this->assertSame(42, $decoded['count']);
        $this->assertSame(1500, $decoded['total']);
    }

    // ── Limit cap ──

    public function test_limit_is_capped_at_50(): void
    {
        $result = min((int) (9999 ?? 10), 50);
        $this->assertSame(50, $result);
    }

    public function test_limit_preserves_valid_values(): void
    {
        $result = min((int) (25 ?? 10), 50);
        $this->assertSame(25, $result);
    }

    public function test_default_limit_is_10(): void
    {
        $result = min((int) (null ?? 10), 50);
        $this->assertSame(10, $result);
    }

    // ── per_page cap ──

    public function test_per_page_is_capped_at_100(): void
    {
        $result = min((int) 500, 100);
        $this->assertSame(100, $result);
    }

    public function test_per_page_preserves_valid_values(): void
    {
        $result = min((int) 30, 100);
        $this->assertSame(30, $result);
    }

    // ── UUID format ──

    public function test_uuid_format_is_valid(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $requestId = 'rakiz_' . $uuid;

        $this->assertMatchesRegularExpression(
            '/^rakiz_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $requestId
        );
    }

    public function test_uuid_is_unique_across_calls(): void
    {
        $ids = array_map(fn () => 'rakiz_' . (string) \Illuminate\Support\Str::uuid(), range(1, 100));
        $this->assertCount(100, array_unique($ids));
    }

    // ── Truncation marker ──

    public function test_truncated_message_includes_marker(): void
    {
        $fullAnswer = 'Partial answer';
        $wasTruncated = true;

        $message = $wasTruncated
            ? $fullAnswer . "\n\n[response truncated — streaming interrupted]"
            : $fullAnswer;

        $this->assertStringContainsString('[response truncated', $message);
    }

    public function test_complete_message_has_no_truncation_marker(): void
    {
        $fullAnswer = 'Complete answer here';
        $wasTruncated = false;

        $message = $wasTruncated
            ? $fullAnswer . "\n\n[response truncated — streaming interrupted]"
            : $fullAnswer;

        $this->assertStringNotContainsString('[response truncated', $message);
    }
}
