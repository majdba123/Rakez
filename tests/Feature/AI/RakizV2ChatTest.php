<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RakizV2ChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('use-ai-assistant');
    }

    public function test_v2_chat_returns_200_and_schema_valid_json_when_openai_returns_completed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $validOutput = json_encode([
            'answer_markdown' => 'Test answer.',
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => ['had_denied_request' => false, 'reason' => ''],
        ]);

        OpenAI::fake([
            \OpenAI\Responses\Responses\CreateResponse::fake([
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'output_text', 'text' => $validOutput, 'annotations' => []],
                        ],
                    ],
                ],
                'status' => 'completed',
            ]),
        ]);

        $response = $this->postJson('/api/ai/v2/chat', [
            'message' => 'How many leads?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'answer_markdown',
                'confidence',
                'sources',
                'links',
                'suggested_actions',
                'follow_up_questions',
                'access_notes' => ['had_denied_request', 'reason'],
            ],
        ]);
        $data = $response->json('data');
        $this->assertArrayHasKey('answer_markdown', $data);
        $this->assertArrayHasKey('confidence', $data);
        $this->assertContains($data['confidence'], ['high', 'medium', 'low']);
        if ($data['confidence'] === 'high') {
            $this->assertSame('Test answer.', $data['answer_markdown']);
        }
    }

    public function test_v2_chat_requires_use_ai_assistant_permission(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/v2/chat', ['message' => 'Hello']);

        $response->assertStatus(403);
    }

    public function test_v2_chat_validates_message_required(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/v2/chat', []);

        $response->assertStatus(422);
    }

    public function test_v2_chat_stores_and_retrieves_session_history(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $validOutput = json_encode([
            'answer_markdown' => 'Stored answer.',
            'confidence' => 'high',
            'sources' => [],
            'links' => [],
            'suggested_actions' => [],
            'follow_up_questions' => [],
            'access_notes' => ['had_denied_request' => false, 'reason' => ''],
        ]);

        OpenAI::fake([
            CreateResponse::fake([
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_1',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            ['type' => 'output_text', 'text' => $validOutput, 'annotations' => []],
                        ],
                    ],
                ],
                'status' => 'completed',
            ]),
        ]);

        $chat = $this->postJson('/api/ai/v2/chat', [
            'message' => 'Store this message',
        ]);

        $chat->assertStatus(200);
        $sessionId = $chat->json('data.session_id');
        $this->assertNotEmpty($sessionId);

        $sessions = $this->getJson('/api/ai/v2/conversations');
        $sessions->assertStatus(200);
        $sessions->assertJsonPath('success', true);
        $this->assertNotEmpty($sessions->json('data'));

        $messages = $this->getJson("/api/ai/v2/conversations/{$sessionId}/messages");
        $messages->assertStatus(200);
        $messages->assertJsonPath('success', true);
        $messages->assertJsonCount(2, 'data');
        $messages->assertJsonPath('data.0.role', 'user');
        $messages->assertJsonPath('data.1.role', 'assistant');
    }
}
