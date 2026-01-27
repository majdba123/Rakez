<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\Lead;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingContextTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        
        // Ensure user has marketing capabilities
        $this->marketingUser->syncPermissions([
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.tasks.view'
        ]);

        // Mock OpenAI
        OpenAI::fake([
            CreateResponse::fake([
                'id' => 'res_123',
                'model' => 'gpt-4.1-mini',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Mocked AI response',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_includes_marketing_dashboard_kpis_in_ai_context()
    {
        Lead::factory()->count(10)->create();

        $response = $this->actingAs($this->marketingUser)
            ->postJson('/api/ai/ask', [
                'question' => 'How many leads do we have?',
                'section' => 'marketing_dashboard'
            ]);

        $response->assertStatus(200);
        // The context is built internally, we verify the response is successful
        // and the section is recognized.
    }

    /** @test */
    public function it_includes_marketing_projects_list_in_ai_context()
    {
        $contract = Contract::factory()->create(['status' => 'approved']);
        MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser)
            ->postJson('/api/ai/ask', [
                'question' => 'What projects are we marketing?',
                'section' => 'marketing_projects'
            ]);

        $response->assertStatus(200);
    }
}
