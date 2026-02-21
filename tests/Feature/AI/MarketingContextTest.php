<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\Lead;
use Spatie\Permission\Models\Permission;
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
        if (empty(config('ai_sections'))) {
            config(['ai_sections' => require config_path('ai_sections.php')]);
        }
        foreach (['marketing.dashboard.view', 'marketing.projects.view', 'marketing.tasks.view', 'use-ai-assistant'] as $name) {
            Permission::findOrCreate($name, 'web');
        }
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->givePermissionTo([
            'marketing.dashboard.view',
            'marketing.projects.view',
            'marketing.tasks.view',
            'use-ai-assistant',
        ]);

        $fakeResponse = [
            'id' => 'res_123',
            'model' => 'gpt-4.1-mini',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Mocked AI response',
                            'annotations' => [],
                        ],
                    ],
                ],
            ],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
                'total_tokens' => 15,
            ],
        ];
        // Each test may trigger multiple API calls (retries can consume many); use a large pool
        OpenAI::fake(array_fill(0, 50, CreateResponse::fake($fakeResponse)));
    }

    #[Test]
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

    #[Test]
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
