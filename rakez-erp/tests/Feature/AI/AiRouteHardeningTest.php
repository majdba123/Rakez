<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\AIAssistantService;
use App\Services\AI\Voice\VoiceAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as IlluminateRoute;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiRouteHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_all_ai_chat_entrypoints_require_use_ai_assistant_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/ai/ask', ['question' => 'x'])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to use the AI assistant.',
            ]);

        $this->postJson('/api/ai/chat', ['message' => 'x'])
            ->assertStatus(403);

        $this->post('/api/ai/voice/chat', [], ['Accept' => 'application/json'])
            ->assertStatus(403);

        $this->getJson('/api/ai/drafts/flows')
            ->assertStatus(403);

        $this->postJson('/api/ai/drafts/prepare', ['message' => 'x'])
            ->assertStatus(403);

        $this->getJson('/api/ai/write-actions/catalog')
            ->assertStatus(403);

        $this->postJson('/api/ai/write-actions/propose', ['action_key' => 'task.create'])
            ->assertStatus(403);

        $this->postJson('/api/ai/write-actions/preview', ['action_key' => 'task.create', 'proposal' => []])
            ->assertStatus(403);

        $this->postJson('/api/ai/write-actions/confirm', [
            'action_key' => 'task.create',
            'proposal' => [],
            'confirmation_phrase' => 'confirm_draft_only',
        ])->assertStatus(403);

        $this->postJson('/api/ai/write-actions/reject', ['action_key' => 'task.create'])
            ->assertStatus(403);

        $this->postJson('/api/ai/tools/chat', ['message' => 'x'])
            ->assertStatus(403);

        $this->postJson('/api/ai/tools/stream', ['message' => 'x'])
            ->assertStatus(403);

        $this->postJson('/api/ai/assistant/chat', ['message' => 'x'])
            ->assertStatus(403);
    }

    public function test_ask_route_redacts_pii_before_hitting_service(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $mock = Mockery::mock(AIAssistantService::class);
        $mock->shouldReceive('ask')
            ->once()
            ->withArgs(function (string $question, User $resolvedUser, $section, array $context, array $runtime = []) use ($user): bool {
                return $resolvedUser->is($user)
                    && $question === 'العميل هويته [REDACTED_NATIONAL_ID] وجواله [REDACTED_PHONE]'
                    && $section === 'general'
                    && $context === []
                    && $runtime === ['provider' => null];
            })
            ->andReturn([
                'message' => 'ok',
                'session_id' => '11111111-1111-1111-1111-111111111111',
                'conversation_id' => null,
                'meta' => [],
            ]);
        $mock->shouldReceive('suggestions')->once()->with('general')->andReturn([]);

        $this->app->instance(AIAssistantService::class, $mock);

        $this->postJson('/api/ai/ask', [
            'question' => 'العميل هويته 1098765432 وجواله 0512345678',
            'section' => 'general',
        ])->assertOk();
    }

    public function test_voice_route_redacts_fallback_text_before_hitting_service(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $mock = Mockery::mock(VoiceAssistantService::class);
        $mock->shouldReceive('handle')
            ->once()
            ->withArgs(function (User $resolvedUser, array $payload) use ($user): bool {
                return $resolvedUser->is($user)
                    && $payload['fallback_text'] === 'كلم العميل على [REDACTED_PHONE]'
                    && $payload['context']['note'] === 'هوية [REDACTED_NATIONAL_ID]';
            })
            ->andReturn([
                'input' => [
                    'audio_uploaded' => false,
                    'audio_persisted' => false,
                    'fallback_text_provided' => true,
                    'fallback_text_used' => true,
                ],
                'transcript' => [
                    'text' => 'كلم العميل على [REDACTED_PHONE]',
                    'source' => 'fallback_text',
                    'language' => null,
                    'duration' => null,
                    'fallback_text_used' => true,
                ],
                'assistant' => [
                    'message' => 'ok',
                    'session_id' => '11111111-1111-1111-1111-111111111111',
                    'conversation_id' => null,
                    'authoritative' => true,
                    'meta' => [],
                ],
                'speech' => [
                    'requested' => false,
                    'generated' => false,
                    'audio' => null,
                    'error_code' => null,
                ],
            ]);

        $this->app->instance(VoiceAssistantService::class, $mock);

        $this->post('/api/ai/voice/chat', [
            'fallback_text' => 'كلم العميل على 0512345678',
            'context' => [
                'note' => 'هوية 1098765432',
            ],
        ], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_ai_routes_are_registered_once_and_use_expected_middlewares(): void
    {
        $askRoute = $this->routeFor('POST', 'api/ai/ask');
        $voiceRoute = $this->routeFor('POST', 'api/ai/voice/chat');
        $draftCatalogRoute = $this->routeFor('GET', 'api/ai/drafts/flows');
        $draftPrepareRoute = $this->routeFor('POST', 'api/ai/drafts/prepare');
        $writeCatalogRoute = $this->routeFor('GET', 'api/ai/write-actions/catalog');
        $writeProposeRoute = $this->routeFor('POST', 'api/ai/write-actions/propose');
        $writePreviewRoute = $this->routeFor('POST', 'api/ai/write-actions/preview');
        $writeConfirmRoute = $this->routeFor('POST', 'api/ai/write-actions/confirm');
        $writeRejectRoute = $this->routeFor('POST', 'api/ai/write-actions/reject');
        $assistantRoute = $this->routeFor('POST', 'api/ai/assistant/chat');

        $askRouteCount = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route) => in_array('POST', $route->methods(), true) && $route->uri() === 'api/ai/ask')
            ->count();

        $this->assertSame(1, $askRouteCount);
        $this->assertContains('auth:sanctum', $askRoute->gatherMiddleware());
        $this->assertContains('throttle:ai-assistant', $askRoute->gatherMiddleware());
        $this->assertContains('ai.assistant', $askRoute->gatherMiddleware());
        $this->assertContains('ai.redact', $askRoute->gatherMiddleware());

        $this->assertContains('auth:sanctum', $voiceRoute->gatherMiddleware());
        $this->assertContains('throttle:ai-assistant', $voiceRoute->gatherMiddleware());
        $this->assertContains('ai.assistant', $voiceRoute->gatherMiddleware());
        $this->assertContains('ai.redact', $voiceRoute->gatherMiddleware());

        foreach ([$draftCatalogRoute, $draftPrepareRoute, $writeCatalogRoute, $writeProposeRoute, $writePreviewRoute, $writeConfirmRoute, $writeRejectRoute] as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('throttle:ai-assistant', $route->gatherMiddleware());
            $this->assertContains('ai.assistant', $route->gatherMiddleware());
            $this->assertContains('ai.redact', $route->gatherMiddleware());
        }

        $this->assertContains('auth:sanctum', $assistantRoute->gatherMiddleware());
        $this->assertContains('throttle:ai-assistant', $assistantRoute->gatherMiddleware());
        $this->assertContains('ai.assistant', $assistantRoute->gatherMiddleware());
        $this->assertContains('ai.redact', $assistantRoute->gatherMiddleware());
    }

    private function routeFor(string $method, string $uri): IlluminateRoute
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn (IlluminateRoute $route) => in_array($method, $route->methods(), true) && $route->uri() === $uri);

        $this->assertNotNull($route, "Route [{$method} {$uri}] was not found.");

        return $route;
    }
}
