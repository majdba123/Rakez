<?php

namespace Tests\Feature\AI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class ContextValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (empty(config('ai_sections'))) {
            config(['ai_sections' => require config_path('ai_sections.php')]);
        }
    }

    public function test_context_validation_accepts_valid_contract_id(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('contracts.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 123,
            ],
        ]);

        $response->assertOk();
    }

    public function test_context_validation_rejects_invalid_contract_id_type(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('contracts.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 'invalid',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['context.contract_id']);
    }

    public function test_context_validation_rejects_contract_id_below_min(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('contracts.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [
                'contract_id' => 0,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['context.contract_id']);
    }

    public function test_context_validation_accepts_valid_unit_id(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('units.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user->givePermissionTo(['units.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'units',
            'context' => [
                'contract_id' => 123,
                'unit_id' => 456,
            ],
        ]);

        $response->assertOk();
    }

    public function test_context_validation_rejects_invalid_unit_id(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('units.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['units.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'units',
            'context' => [
                'contract_id' => 123,
                'unit_id' => 'invalid',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['context.unit_id']);
    }

    public function test_context_validation_handles_missing_section(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'context' => [
                'contract_id' => 123,
            ],
        ]);

        // Should pass validation when section is missing (no context rules applied)
        $response->assertOk();
    }

    public function test_context_validation_handles_empty_context(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('contracts.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('use-ai-assistant', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);
        Sanctum::actingAs($user);

        OpenAI::fake([
            $this->fakeResponse('Answer'),
        ]);

        $response = $this->postJson('/api/ai/ask', [
            'question' => 'Test question',
            'section' => 'contracts',
            'context' => [],
        ]);

        $response->assertOk();
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
