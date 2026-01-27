<?php

namespace Tests\Feature\Marketing;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    public function test_unauthorized_user_cannot_access_marketing_dashboard()
    {
        $user = User::factory()->create(['type' => 'sales']); // Not marketing/admin
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/marketing/dashboard');

        $response->assertStatus(403);
    }

    public function test_marketing_user_can_access_marketing_dashboard()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->assignRole('marketing');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/marketing/dashboard');

        $response->assertStatus(200);
    }

    public function test_unauthorized_user_cannot_create_lead()
    {
        $user = User::factory()->create(['type' => 'sales']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/marketing/leads', [
            'name' => 'Test Lead',
            'contact_info' => 'test@example.com',
            'project_id' => 1
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_lead_payload_returns_422()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->assignRole('marketing');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/marketing/leads', [
            'name' => '', // Required
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'contact_info', 'project_id']);
    }
}
