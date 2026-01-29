<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingTaskTest extends TestCase
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
    public function it_can_create_marketing_task()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser)
            ->postJson('/api/marketing/tasks', [
                'marketing_project_id' => $project->id,
                'contract_id' => $contract->id,
                'task_name' => 'Social Media Post',
                'marketer_id' => $this->marketingUser->id,
                'due_date' => now()->toDateString()
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('marketing_tasks', [
            'task_name' => 'Social Media Post'
        ]);
    }

    #[Test]
    public function it_can_update_task_status()
    {
        $contract = Contract::factory()->create();
        $task = MarketingTask::create([
            'contract_id' => $contract->id,
            'task_name' => 'Old Task',
            'marketer_id' => $this->marketingUser->id,
            'status' => 'new',
            'created_by' => $this->marketingUser->id,
            'due_date' => now()->toDateString()
        ]);

        $response = $this->actingAs($this->marketingUser)
            ->patchJson("/api/marketing/tasks/{$task->id}/status", [
                'status' => 'completed'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('completed', $task->fresh()->status);
    }
}
