<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Lead;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->assignRole('marketing');
    }

    #[Test]
    public function it_can_create_lead()
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->marketingUser)
            ->postJson('/api/marketing/leads', [
                'name' => 'Test Lead',
                'contact_info' => 'test@example.com',
                'source' => 'Facebook',
                'project_id' => $contract->id,
                'assigned_to' => $this->marketingUser->id
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('leads', [
            'name' => 'Test Lead',
            'contact_info' => 'test@example.com'
        ]);
    }

    #[Test]
    public function it_can_list_leads()
    {
        Lead::factory()->count(3)->create();

        $response = $this->actingAs($this->marketingUser)
            ->getJson('/api/marketing/leads');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }
}
