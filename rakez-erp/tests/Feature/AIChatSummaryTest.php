<?php

namespace Tests\Feature;

use App\Models\AIConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class AIChatSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_is_created_after_threshold(): void
    {
        config()->set('ai_assistant.chat.summary_every', 8);
        config()->set('ai_assistant.chat.summary_window', 2);
        config()->set('ai_assistant.chat.tail_messages', 2);

        OpenAI::fake([
            $this->fakeResponse('First answer.'),
            $this->fakeResponse('Second answer.'),
            $this->fakeResponse('Third answer.'),
            $this->fakeResponse('Fourth answer.'),
            $this->fakeResponse('Summary content.'),
        ]);

        $user = User::factory()->create(['type' => 'developer']);
        Sanctum::actingAs($user);

        $sessionId = '11111111-1111-1111-1111-111111111111';

        $this->postJson('/api/ai/chat', [
            'message' => 'First question',
            'session_id' => $sessionId,
            'section' => 'general',
        ])->assertOk();

        $this->postJson('/api/ai/chat', [
            'message' => 'Second question',
            'session_id' => $sessionId,
            'section' => 'general',
        ])->assertOk();

        $this->postJson('/api/ai/chat', [
            'message' => 'Third question',
            'session_id' => $sessionId,
            'section' => 'general',
        ])->assertOk();

        $this->postJson('/api/ai/chat', [
            'message' => 'Fourth question',
            'session_id' => $sessionId,
            'section' => 'general',
        ])->assertOk();

        $summaryCount = AIConversation::query()
            ->where('session_id', $sessionId)
            ->where('is_summary', true)
            ->count();

        $this->assertSame(1, $summaryCount);
    }

    private function fakeResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
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
        ]);
    }
}
