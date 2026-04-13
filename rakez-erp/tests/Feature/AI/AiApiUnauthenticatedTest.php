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
        $this->post('/api/ai/voice/chat', [], ['Accept' => 'application/json'])->assertUnauthorized();
        $this->postJson('/api/ai/realtime/sessions', [])->assertUnauthorized();
        $this->getJson('/api/ai/realtime/sessions/00000000-0000-0000-0000-000000000000')->assertUnauthorized();
        $this->postJson('/api/ai/realtime/sessions/00000000-0000-0000-0000-000000000000/client-events', [])->assertUnauthorized();
        $this->postJson('/api/ai/realtime/sessions/00000000-0000-0000-0000-000000000000/bridge/start', [])->assertUnauthorized();
        $this->postJson('/api/ai/realtime/sessions/00000000-0000-0000-0000-000000000000/bridge/stop', [])->assertUnauthorized();
        $this->getJson('/api/ai/drafts/flows')->assertUnauthorized();
        $this->postJson('/api/ai/drafts/prepare', ['message' => 'x'])->assertUnauthorized();
        $this->getJson('/api/ai/write-actions/catalog')->assertUnauthorized();
        $this->postJson('/api/ai/write-actions/propose', ['action_key' => 'task.create'])->assertUnauthorized();
        $this->postJson('/api/ai/write-actions/preview', ['action_key' => 'task.create', 'proposal' => []])->assertUnauthorized();
        $this->postJson('/api/ai/write-actions/confirm', ['action_key' => 'task.create', 'proposal' => [], 'confirmation_phrase' => 'confirm_draft_only'])->assertUnauthorized();
        $this->postJson('/api/ai/write-actions/reject', ['action_key' => 'task.create'])->assertUnauthorized();
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
