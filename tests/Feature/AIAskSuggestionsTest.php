<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AIAskSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_returns_section_suggestions(): void
    {
        OpenAI::fake([
            $this->fakeResponse('Here is a helpful answer.'),
        ]);

        $user = User::factory()->create(['type' => 'developer']);
        Permission::firstOrCreate(['name' => 'use-ai-assistant', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'contracts.view', 'guard_name' => 'web']);
        $user->givePermissionTo('use-ai-assistant');
        $user->givePermissionTo('contracts.view');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'How do I create a contract?',
            'section' => 'contracts',
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'suggestions' => config('ai_sections.contracts.suggestions'),
        ]);
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
