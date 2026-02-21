<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\Contract;
use App\Models\SalesReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccessExplanationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permissions exist (use-ai-assistant required to hit /api/ai/ask)
        Permission::findOrCreate('contracts.view', 'web');
        Permission::findOrCreate('contracts.view_all', 'web');
        Permission::findOrCreate('sales.reservations.view', 'web');
        Permission::findOrCreate('use-ai-assistant', 'web');
    }

    public function test_allowed_case(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);

        $contract = Contract::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see contract {$contract->id}?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.allowed', true);
        $response->assertJsonPath('data.access_summary.reason_code', 'allowed');
        $response->assertJsonStructure([
            'data' => [
                'message',
                'steps',
                'links',
                'access_summary',
                'meta',
            ]
        ]);
        $this->assertEquals([], $response->json('data.links'));
    }

    public function test_no_type_access_neutral_denial(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant'); // pass route middleware only

        $contract = Contract::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see contract {$contract->id}?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.allowed', false);
        $response->assertJsonPath('data.access_summary.reason_code', 'resource_not_found_or_not_allowed');
        $response->assertSee('We can\'t verify this resource or you don\'t have access to it.', false);
    }

    public function test_ownership_mismatch(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userB->givePermissionTo(['sales.reservations.view', 'use-ai-assistant']);

        // Reservation owned by user A
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $userA->id
        ]);

        Sanctum::actingAs($userB);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see reservation {$reservation->id}?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.allowed', false);
        $response->assertJsonPath('data.access_summary.reason_code', 'ownership_mismatch');
        $response->assertSee('Your current permissions do not allow this action on this specific resource.');
    }

    public function test_resource_not_found(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['contracts.view', 'use-ai-assistant']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see contract 99999?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.allowed', false);
        $response->assertJsonPath('data.access_summary.reason_code', 'resource_not_found');
    }

    public function test_invalid_request_missing_id_or_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see this?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.reason_code', 'invalid_request');
        $response->assertSee('I detected you are asking about an access issue');
    }
}
