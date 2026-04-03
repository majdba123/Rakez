<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;

/**
 * سيناريوهات مصفوفة Role × Section × Permission (بدون OpenAI حقيقي حيثما أمكن).
 *
 * @see tests/AI_SCENARIO_MATRIX.md
 */
class AiScenarioMatrixFeatureTest extends TestCase
{
    use CreatesUsersWithBootstrapRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function sectionKeysFromResponse(array $json): array
    {
        $sections = $json['data'] ?? [];
        if (! is_array($sections)) {
            return [];
        }

        $keys = [];
        foreach ($sections as $item) {
            if (is_array($item) && isset($item['key'])) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    public function test_default_role_sections_exclude_marketing_dashboard(): void
    {
        $user = $this->createUserWithBootstrapRole('default', ['type' => 'default']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/sections');
        $response->assertOk();

        $keys = $this->sectionKeysFromResponse($response->json());
        $this->assertNotContains('marketing_dashboard', $keys, 'default role must not see marketing AI section without marketing.dashboard.view');
    }

    public function test_marketing_role_sections_include_marketing_dashboard(): void
    {
        $user = $this->createUserWithBootstrapRole('marketing', ['type' => 'marketing']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/sections');
        $response->assertOk();

        $keys = $this->sectionKeysFromResponse($response->json());
        $this->assertContains('marketing_dashboard', $keys);
    }

    public function test_ask_with_section_sales_returns_403_for_default_bootstrap_user(): void
    {
        $user = $this->createUserWithBootstrapRole('default', ['type' => 'default']);
        Sanctum::actingAs($user);

        OpenAI::fake([$this->fakeOpenAiResponse('should not run')]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'What is sales KPI?',
            'section' => 'sales',
        ]);

        $response->assertStatus(403);
        $this->assertSame('UNAUTHORIZED_SECTION', $response->json('error_code'));
    }

    public function test_ask_with_section_roas_optimizer_returns_403_for_marketing_user_missing_accounting_capability(): void
    {
        $user = $this->createUserWithBootstrapRole('marketing', ['type' => 'marketing']);
        Sanctum::actingAs($user);

        OpenAI::fake([$this->fakeOpenAiResponse('should not run')]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'What is ROAS?',
            'section' => 'roas_optimizer',
        ]);

        $response->assertStatus(403);
        $this->assertSame('UNAUTHORIZED_SECTION', $response->json('error_code'));
    }

    public function test_tools_chat_rejects_message_longer_than_16000_chars(): void
    {
        $user = $this->createUserWithBootstrapRole('sales', ['type' => 'sales']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => str_repeat('x', 16001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_whitespace_only_question_fails_validation_as_empty(): void
    {
        $user = $this->createUserWithBootstrapRole('default', ['type' => 'default']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "   \t  ",
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['question']);
    }

    public function test_malformed_json_body_returns_client_error(): void
    {
        $user = $this->createUserWithBootstrapRole('default', ['type' => 'default']);
        Sanctum::actingAs($user);

        $response = $this->call(
            'POST',
            '/api/ai/ask',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            '{ not-json'
        );

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_tools_stream_returns_forbidden_payload_without_use_ai_permission(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('contracts.view', 'web');
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('contracts.view');
        Sanctum::actingAs($user);

        $response = $this->post('/api/ai/tools/stream', [
            'message' => 'hello',
        ], [
            'Accept' => 'text/event-stream',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('You do not have permission to use the AI assistant.', $response->streamedContent());
    }

    private function fakeOpenAiResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'id' => 'resp_test_'.uniqid(),
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_test',
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
        ]);
    }
}
