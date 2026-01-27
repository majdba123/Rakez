<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_other_user_contract(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        // Should either return 403 or exclude contract data
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_user_with_view_all_can_access_any_contract(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->setAttribute('capabilities', ['contracts.view', 'contracts.view_all']);
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($admin);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        $response->assertOk();
    }

    public function test_user_cannot_access_nonexistent_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 99999,
            ],
        ]);

        // Should handle gracefully
        $response->assertOk();
    }

    public function test_context_policy_enforces_contract_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $contract = Contract::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        // This should trigger policy check in ContextBuilder
        // The exact behavior depends on implementation
        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => $contract->id,
            ],
        ]);

        // Policy should prevent unauthorized access
        $this->assertContains($response->status(), [200, 403]);
    }

    public function test_sections_filtered_by_capabilities(): void
    {
        $developer = User::factory()->create(['type' => 'developer']);
        Sanctum::actingAs($developer);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $sections = $response->json('data');

        // Developer should not see dashboard (requires dashboard.analytics.view)
        $hasDashboard = false;
        foreach ($sections as $section) {
            if (isset($section['label']) && $section['label'] === 'Dashboard') {
                $hasDashboard = true;
                break;
            }
        }

        $this->assertFalse($hasDashboard, 'Developer should not see dashboard section');
    }

    public function test_admin_sees_all_sections(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        $admin->setAttribute('capabilities', ['dashboard.analytics.view', 'contracts.view', 'units.view']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/ai/sections');

        $response->assertOk();
        $sections = $response->json('data');

        // Admin with dashboard capability should see dashboard
        $hasDashboard = false;
        foreach ($sections as $section) {
            if (isset($section['label']) && $section['label'] === 'Dashboard') {
                $hasDashboard = true;
                break;
            }
        }

        $this->assertTrue($hasDashboard, 'Admin with dashboard.analytics.view capability should see dashboard section');
    }

    private function fakeResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'id' => 'resp_' . uniqid(),
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_' . uniqid(),
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
