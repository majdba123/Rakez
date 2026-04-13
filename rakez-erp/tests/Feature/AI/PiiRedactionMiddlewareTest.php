<?php

namespace Tests\Feature\AI;

use App\Http\Middleware\RedactPiiFromAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class PiiRedactionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_redacts_national_id_from_message(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'العميل رقم هويته 1098765432 يريد شراء وحدة',
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertStringNotContainsString('1098765432', $req->input('message'));
            $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $req->input('message'));

            return new Response('ok');
        });
    }

    public function test_middleware_redacts_phone_from_question(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/ask', 'POST', [
            'question' => 'ابحث عن العميل جوال 0512345678',
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertStringNotContainsString('0512345678', $req->input('question'));
            $this->assertStringContainsString('[REDACTED_PHONE]', $req->input('question'));

            return new Response('ok');
        });
    }

    public function test_middleware_does_not_modify_non_ai_fields(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
            'name' => '0512345678', // Should not be redacted
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertEquals('0512345678', $req->input('name'));

            return new Response('ok');
        });
    }

    public function test_middleware_redacts_nested_context_values(): void
    {
        $middleware = new RedactPiiFromAi;
        $request = Request::create('/api/ai/chat', 'POST', [
            'message' => 'hello',
            'context' => [
                'customer_note' => 'هوية العميل 1098765432',
                'nested' => [
                    'phone' => 'اتصل على 0512345678',
                ],
            ],
        ]);

        $middleware->handle($request, function ($req) {
            $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $req->input('context.customer_note'));
            $this->assertStringContainsString('[REDACTED_PHONE]', $req->input('context.nested.phone'));

            return new Response('ok');
        });
    }
}
