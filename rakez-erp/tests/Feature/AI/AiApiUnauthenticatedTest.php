<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI routes under /api/ai require auth:sanctum — unauthenticated clients must get 401.
 */
class AiApiUnauthenticatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_routes_return_401_without_auth(): void
    {
        $this->postJson('/api/ai/ask', ['question' => 'x'])->assertUnauthorized();
        $this->postJson('/api/ai/chat', ['message' => 'x'])->assertUnauthorized();
        $this->postJson('/api/ai/tools/chat', ['message' => 'x'])->assertUnauthorized();
        $this->postJson('/api/ai/v2/chat', ['message' => 'x'])->assertUnauthorized();
        $this->postJson('/api/ai/tools/stream', ['message' => 'x'])->assertUnauthorized();
        $this->getJson('/api/ai/conversations')->assertUnauthorized();
        $this->getJson('/api/ai/sections')->assertUnauthorized();
        $this->deleteJson('/api/ai/conversations/00000000-0000-0000-0000-000000000000')->assertUnauthorized();
        $this->getJson('/api/ai/documents')->assertUnauthorized();
        $this->postJson('/api/ai/documents/search', [])->assertUnauthorized();
        $this->postJson('/api/ai/assistant/chat', [])->assertUnauthorized();
    }
}
