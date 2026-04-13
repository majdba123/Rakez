<?php

namespace Tests\Unit\AI;

use Anthropic\Messages\Message;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\ToolUseBlock;
use Anthropic\Messages\Usage;
use App\Models\User;
use App\Services\AI\AiIndexingService;
use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\AiProviderResolver;
use App\Services\AI\Anthropic\AnthropicGateway;
use App\Services\AI\CatalogService;
use App\Services\AI\PromptVersionManager;
use App\Services\AI\RakizAiOrchestrator;
use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RakizAiOrchestratorAnthropicTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_anthropic_provider_can_complete_tool_loop_and_normalize_output(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        config([
            'anthropic.enabled' => true,
            'anthropic.model' => 'claude-3-5-sonnet-latest',
            'anthropic.max_output_tokens' => 1200,
            'anthropic.temperature' => 0.0,
            'ai_assistant.v2.tool_loop.max_tool_calls' => 3,
            'ai_assistant.v2.tool_gates' => [
                'tool_search_records' => ['permission' => 'use-ai-assistant'],
            ],
        ]);

        $toolRegistry = Mockery::mock(ToolRegistry::class);
        $toolRegistry->shouldReceive('allowedToolNamesForUser')
            ->once()
            ->andReturn(['tool_search_records']);
        $toolRegistry->shouldReceive('execute')
            ->once()
            ->with($user, 'tool_search_records', [
                'query' => 'lead 7',
                'modules' => ['leads'],
                'limit' => 1,
            ])
            ->andReturn([
                'result' => [
                    'data' => [
                        'total_found' => 1,
                    ],
                ],
            ]);

        $indexing = Mockery::mock(AiIndexingService::class);
        $indexing->shouldReceive('redactSecrets')->andReturnUsing(fn ($value) => $value);

        $promptVersions = Mockery::mock(PromptVersionManager::class);
        $promptVersions->shouldReceive('resolve')
            ->once()
            ->andReturn([
                'content' => 'You are a structured assistant.',
                'version_id' => 1,
            ]);

        $anthropic = Mockery::mock(AnthropicGateway::class);
        $anthropic->shouldReceive('messagesCreate')
            ->once()
            ->andReturn([
                'message' => $this->anthropicMessage([
                    ToolUseBlock::with(
                        id: 'toolu_1',
                        input: [
                            'query' => 'lead 7',
                            'modules' => ['leads'],
                            'limit' => 1,
                        ],
                        name: 'tool_search_records',
                    ),
                ], 'tool_use', 20, 5),
                'latency_ms' => 80,
                'credential_source' => 'env_default',
            ]);
        $anthropic->shouldReceive('messagesCreate')
            ->once()
            ->andReturn([
                'message' => $this->anthropicMessage([
                    TextBlock::with(null, json_encode([
                        'answer_markdown' => 'تم العثور على نتيجة واحدة.',
                        'confidence' => 'high',
                        'sources' => [],
                        'links' => [],
                        'suggested_actions' => [],
                        'follow_up_questions' => [],
                        'access_notes' => [
                            'had_denied_request' => false,
                            'reason' => '',
                        ],
                    ], JSON_UNESCAPED_UNICODE)),
                ], 'end_turn', 10, 7),
                'latency_ms' => 90,
                'credential_source' => 'env_default',
            ]);

        $orchestrator = new RakizAiOrchestrator(
            toolRegistry: $toolRegistry,
            indexingService: $indexing,
            openAiGateway: Mockery::mock(AiOpenAiGateway::class),
            promptVersionManager: $promptVersions,
            catalogService: Mockery::mock(CatalogService::class),
            guardrails: null,
            anthropicGateway: $anthropic,
            providerResolver: app(AiProviderResolver::class),
        );

        $result = $orchestrator->chat($user, 'ابحث عن الليد 7', 'sess_1', [
            'provider' => 'anthropic',
            'section' => 'general',
            'policy_snapshot' => ['tool_mode' => 'auto'],
        ]);

        $this->assertSame('تم العثور على نتيجة واحدة.', $result['answer_markdown']);
        $this->assertSame('anthropic', $result['_execution_meta']['provider']);
        $this->assertSame('claude-3-5-sonnet-latest', $result['_execution_meta']['model']);
        $this->assertGreaterThan(0, $result['_execution_meta']['total_tokens']);
    }

    /**
     * @param  array<int, object>  $content
     */
    private function anthropicMessage(array $content, string $stopReason, int $inputTokens, int $outputTokens): Message
    {
        return Message::with(
            id: 'msg_123',
            container: null,
            content: $content,
            model: 'claude-3-5-sonnet-latest',
            stopDetails: null,
            stopReason: $stopReason,
            stopSequence: null,
            usage: Usage::with(
                cacheCreation: null,
                cacheCreationInputTokens: null,
                cacheReadInputTokens: null,
                inferenceGeo: null,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                serverToolUse: null,
                serviceTier: null,
            ),
        );
    }
}
