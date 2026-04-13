<?php

namespace Tests\Feature\AI;

use App\Models\AIConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiBudgetGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    public function test_default_daily_token_budget_is_finite(): void
    {
        $this->assertGreaterThan(0, config('ai_assistant.budgets.per_user_daily_tokens'));
    }

    public function test_ask_route_returns_429_when_daily_budget_is_exhausted(): void
    {
        config(['ai_assistant.budgets.per_user_daily_tokens' => 100]);

        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        AIConversation::create([
            'user_id' => $user->id,
            'session_id' => '11111111-1111-1111-1111-111111111111',
            'role' => 'assistant',
            'message' => 'Prior response',
            'total_tokens' => 100,
        ]);

        OpenAI::fake();

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Will this exceed the budget?',
            'section' => 'general',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error_code', 'ai_budget_exceeded');

        OpenAI::assertNotSent(\OpenAI\Resources\Responses::class);
    }
}
