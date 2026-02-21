<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Lead;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_marketing_user_can_list_leads()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);
        
        Lead::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/leads');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'contact_info', 'source', 'status']
                ]
            ]);
    }

    public function test_marketing_user_can_create_lead()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);
        
        $contract = Contract::factory()->create();

        $leadData = [
            'name' => 'محمد أحمد',
            'contact_info' => '0501234567',
            'source' => 'Facebook',
            'status' => 'new',
            'project_id' => $contract->id,
            'assigned_to' => $user->id,
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/marketing/leads', $leadData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Lead created successfully'
            ]);

        $this->assertDatabaseHas('leads', [
            'name' => 'محمد أحمد',
            'contact_info' => '0501234567'
        ]);
    }

    public function test_marketing_user_can_update_lead()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);
        
        $lead = Lead::factory()->create();

        $updateData = [
            'status' => 'contacted',
            'contact_info' => '0509876543'
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/marketing/leads/{$lead->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lead updated successfully'
            ]);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'contacted'
        ]);
    }

    public function test_non_marketing_user_cannot_access_leads()
    {
        $user = User::factory()->create(['type' => 'sales']);
        $user->syncRoles(['sales']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/leads');

        $response->assertStatus(403);
    }
}
