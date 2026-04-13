<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Services\AI\Anthropic\AnthropicTextProvider;
use App\Services\AI\Data\AiTextResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiProviderSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai_assistant.enabled' => true,
            'ai_assistant.budgets.per_user_daily_tokens' => 0,
        ]);

        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_chat_route_can_use_anthropic_provider_without_touching_openai(): void
    {
        config([
            'anthropic.enabled' => true,
            'ai_assistant.default_provider' => 'openai',
        ]);

        $anthropic = Mockery::mock(AnthropicTextProvider::class);
        $anthropic->shouldReceive('createResponse')
            ->once()
            ->andReturn(new AiTextResponse(
                provider: 'anthropic',
                text: 'Claude says hello.',
                model: 'claude-3-5-sonnet-latest',
                responseId: 'msg_123',
                promptTokens: 10,
                completionTokens: 5,
                totalTokens: 15,
                latencyMs: 90,
                requestId: 'msg_123',
                correlationId: 'corr_123',
            ));
        $this->app->instance(AnthropicTextProvider::class, $anthropic);

        OpenAI::fake();

        Sanctum::actingAs($this->assistantUser());

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hello Claude',
            'provider' => 'anthropic',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Claude says hello.')
            ->assertJsonPath('data.meta.provider', 'anthropic');

        OpenAI::assertNotSent(\OpenAI\Resources\Responses::class);
    }

    public function test_chat_route_defaults_to_openai_when_provider_is_not_selected(): void
    {
        config([
            'ai_assistant.default_provider' => 'openai',
            'anthropic.enabled' => true,
        ]);

        $anthropic = Mockery::mock(AnthropicTextProvider::class);
        $anthropic->shouldReceive('createResponse')->never();
        $this->app->instance(AnthropicTextProvider::class, $anthropic);

        OpenAI::fake([
            $this->fakeOpenAiResponse('OpenAI stays default.'),
        ]);

        Sanctum::actingAs($this->assistantUser());

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hello default provider',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'OpenAI stays default.')
            ->assertJsonPath('data.meta.provider', 'openai');
    }

    public function test_chat_route_fails_safely_when_anthropic_is_disabled(): void
    {
        config([
            'anthropic.enabled' => false,
        ]);

        Sanctum::actingAs($this->assistantUser());

        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Hello Claude',
            'provider' => 'anthropic',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error_code', 'ai_provider_misconfigured');
    }

    private function assistantUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        return $user->fresh();
    }

    private function fakeOpenAiResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'id' => 'resp_123',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'correlation_id' => 'corr_openai',
            ],
        ]);
    }
}
