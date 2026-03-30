<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Tools orchestrator must reject users without use-ai-assistant (no live API).
 */
class AiToolsPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tools_chat_returns_403_without_use_ai_assistant(): void
    {
        Permission::firstOrCreate(['name' => 'contracts.view', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('contracts.view');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/tools/chat', [
            'message' => 'اختبار',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($response->json('success'));
    }
}
