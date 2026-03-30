<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * FormRequest validation for v1 ask/chat (max 2000 per AskQuestionRequest / ChatRequest).
 * No OpenAI calls — validation runs first.
 */
class AiApiValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);
    }

    public function test_ask_requires_question(): void
    {
        $this->postJson('/api/ai/ask', [])->assertStatus(422);
    }

    public function test_chat_requires_message(): void
    {
        $this->postJson('/api/ai/chat', [])->assertStatus(422);
    }

    public function test_tools_chat_requires_message(): void
    {
        $this->postJson('/api/ai/tools/chat', [])->assertStatus(422);
    }
}
