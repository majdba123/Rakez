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

    public function test_context_validation_accepts_valid_contract_id(): void
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
                'contract_id' => 123,
            ],
        ]);

        $response->assertOk();
    }

    public function test_context_validation_rejects_invalid_contract_id_type(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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

        // Should pass validation when section is missing
        $response->assertOk();
    }

    public function test_context_validation_handles_empty_context(): void
    {
        $user = User::factory()->create();
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
