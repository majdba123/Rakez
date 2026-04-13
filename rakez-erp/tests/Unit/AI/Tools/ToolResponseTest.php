<?php

namespace Tests\Unit\AI\Tools;

use App\Services\AI\GuardrailCheck;
use App\Services\AI\Tools\ToolResponse;
use PHPUnit\Framework\TestCase;

class ToolResponseTest extends TestCase
{
    public function test_success_returns_correct_structure(): void
    {
        $result = ToolResponse::success('tool_test', ['key' => 'value'], ['data' => 123]);

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('source_refs', $result);
        $this->assertEquals('tool_test', $result['result']['tool']);
        $this->assertEquals(['key' => 'value'], $result['result']['inputs']);
        $this->assertSame('success', $result['result']['data']['status'] ?? null);
        $this->assertSame('database', $result['result']['data']['data_source'] ?? null);
        $this->assertEquals(123, $result['result']['data']['data']);
        $this->assertEmpty($result['source_refs']);
    }

    public function test_success_envelope_status_wins_over_conflicting_payload_keys(): void
    {
        $result = ToolResponse::success('tool_test', [], [
            'status' => 'would_break_contract_if_merged_wrong',
            'entity' => 'x',
        ]);

        $this->assertSame('success', $result['result']['data']['status'] ?? null);
        $this->assertSame('x', $result['result']['data']['entity'] ?? null);
    }

    public function test_success_with_source_refs(): void
    {
        $refs = [
            ['type' => 'record', 'title' => 'Test Record', 'ref' => 'test:1'],
        ];

        $result = ToolResponse::success('tool_test', [], [], $refs);

        $this->assertCount(1, $result['source_refs']);
        $this->assertEquals('Test Record', $result['source_refs'][0]['title']);
    }

    public function test_success_with_notes(): void
    {
        $result = ToolResponse::success('tool_test', [], [], [], ['Note 1', 'Note 2']);

        $this->assertArrayHasKey('notes', $result['result']);
        $this->assertCount(2, $result['result']['notes']);
    }

    public function test_denied_returns_correct_structure(): void
    {
        $result = ToolResponse::denied('leads.view');

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('source_refs', $result);
        $this->assertSame('denied', $result['result']['status'] ?? null);
        $this->assertFalse($result['result']['allowed']);
        $this->assertStringContainsString('الصلاحية', $result['result']['error']);
        $this->assertEquals('leads.view', $result['result']['required_permission']);
        $this->assertEmpty($result['source_refs']);
    }

    public function test_with_guardrails_appends_check(): void
    {
        $response = ToolResponse::success('tool_test', [], []);

        $check = new GuardrailCheck(
            metric: 'cpl',
            value: 200.0,
            status: 'critical',
            message: 'CPL too high',
            range: ['min' => 15, 'max' => 150],
        );

        $result = ToolResponse::withGuardrails($response, $check);

        $this->assertArrayHasKey('guardrails', $result['result']);
        $this->assertCount(1, $result['result']['guardrails']);
        $this->assertEquals('critical', $result['result']['guardrails'][0]['status']);
    }

    public function test_with_guardrails_appends_multiple_checks(): void
    {
        $response = ToolResponse::success('tool_test', [], []);

        $check1 = new GuardrailCheck('cpl', 200.0, 'critical', 'High', []);
        $check2 = new GuardrailCheck('roi', 50.0, 'warning', 'Low ROI', []);

        $result = ToolResponse::withGuardrails($response, $check1);
        $result = ToolResponse::withGuardrails($result, $check2);

        $this->assertCount(2, $result['result']['guardrails']);
    }

    public function test_error_returns_correct_structure(): void
    {
        $result = ToolResponse::error('Something went wrong');

        $this->assertSame('error', $result['result']['status'] ?? null);
        $this->assertEquals('Something went wrong', $result['result']['error']);
        $this->assertEmpty($result['source_refs']);
    }

    public function test_invalid_arguments_returns_status(): void
    {
        $result = ToolResponse::invalidArguments('bad');

        $this->assertSame('invalid_arguments', $result['result']['status'] ?? null);
        $this->assertSame('bad', $result['result']['error']);
    }

    public function test_unsupported_operation_returns_status(): void
    {
        $result = ToolResponse::unsupportedOperation('nope');

        $this->assertSame('unsupported_operation', $result['result']['status'] ?? null);
    }
}
