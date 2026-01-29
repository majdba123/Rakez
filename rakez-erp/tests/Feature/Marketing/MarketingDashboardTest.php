<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Lead;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->syncRolesFromType();
    }

    #[Test]
    public function it_can_retrieve_dashboard_kpis()
    {
        // Create some data
        Lead::factory()->count(5)->create();
        $project = Contract::factory()->create(['status' => 'approved']);
        MarketingProject::create(['contract_id' => $project->id]);
        
        MarketingTask::create([
            'contract_id' => $project->id,
            'task_name' => 'Test Task',
            'marketer_id' => $this->marketingUser->id,
            'status' => 'completed',
            'created_by' => $this->marketingUser->id,
            'due_date' => now()->toDateString()
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_leads',
                    'available_units_value',
                    'available_units_count',
                    'daily_task_achievement_rate',
                    'daily_deposits_count',
                    'deposit_cost',
                    'total_expected_bookings',
                    'total_expected_booking_value'
                ]
            ]);
            
        $this->assertEquals(5, $response->json('data.total_leads'));
    }
}
