<?php

namespace Tests\Feature\Sales;

use App\Models\Contract;
use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingTaskTest extends TestCase
{
    use RefreshDatabase;

    protected User $leader;
    protected User $marketer;
    protected Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->leader = User::factory()->create([
            'type' => 'sales',
            'is_manager' => true,
            'team' => 'Team Alpha',
        ]);
        $this->leader->assignRole('sales_leader');

        $this->marketer = User::factory()->create([
            'type' => 'sales',
            'is_manager' => false,
            'team' => 'Team Alpha',
        ]);
        $this->marketer->assignRole('sales');

        $this->contract = Contract::factory()->create(['status' => 'ready']);
        
        // Assign leader to project
        \App\Models\SalesProjectAssignment::create([
            'leader_id' => $this->leader->id,
            'contract_id' => $this->contract->id,
            'assigned_by' => $this->leader->id,
        ]);
    }

    public function test_leader_can_create_marketing_task()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Direct communication campaign',
            'marketer_id' => $this->marketer->id,
            'participating_marketers_count' => 4,
            'design_link' => 'https://example.com/design.pdf',
            'design_number' => 'D-001',
            'design_description' => 'Social media campaign design',
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('marketing_tasks', [
            'contract_id' => $this->contract->id,
            'task_name' => 'Direct communication campaign',
            'marketer_id' => $this->marketer->id,
            'created_by' => $this->leader->id,
            'status' => 'new',
        ]);
    }

    public function test_marketer_cannot_create_marketing_task()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Test task',
            'marketer_id' => $this->marketer->id,
            'participating_marketers_count' => 4,
        ];

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(403);
    }

    public function test_leader_can_update_task_status()
    {
        $task = MarketingTask::factory()->create([
            'contract_id' => $this->contract->id,
            'marketer_id' => $this->marketer->id,
            'created_by' => $this->leader->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->patchJson("/api/sales/marketing-tasks/{$task->id}", [
                'status' => 'in_progress',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('marketing_tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_marketer_cannot_update_task_status()
    {
        $task = MarketingTask::factory()->create([
            'contract_id' => $this->contract->id,
            'marketer_id' => $this->marketer->id,
            'created_by' => $this->leader->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->marketer, 'sanctum')
            ->patchJson("/api/sales/marketing-tasks/{$task->id}", [
                'status' => 'completed',
            ]);

        $response->assertStatus(403);
    }

    public function test_leader_can_list_task_projects()
    {
        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson('/api/sales/tasks/projects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_leader_can_view_project_for_tasks()
    {
        $response = $this->actingAs($this->leader, 'sanctum')
            ->getJson("/api/sales/tasks/projects/{$this->contract->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'contract_id',
                    'project_name',
                    'project_description',
                    'montage_designs',
                ],
            ]);
    }

    public function test_create_task_validates_required_fields()
    {
        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'contract_id',
                'task_name',
                'marketer_id',
            ]);
    }

    public function test_task_defaults_participating_marketers_count_to_4()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Test task',
            'marketer_id' => $this->marketer->id,
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(201);

        $task = MarketingTask::first();
        $this->assertEquals(4, $task->participating_marketers_count);
    }

    public function test_task_optional_fields_can_be_null()
    {
        $data = [
            'contract_id' => $this->contract->id,
            'task_name' => 'Test task',
            'marketer_id' => $this->marketer->id,
            'participating_marketers_count' => 3,
        ];

        $response = $this->actingAs($this->leader, 'sanctum')
            ->postJson('/api/sales/marketing-tasks', $data);

        $response->assertStatus(201);

        $task = MarketingTask::first();
        $this->assertNull($task->design_link);
        $this->assertNull($task->design_number);
        $this->assertNull($task->design_description);
    }

    public function test_update_task_validates_status_enum()
    {
        $task = MarketingTask::factory()->create([
            'contract_id' => $this->contract->id,
            'marketer_id' => $this->marketer->id,
            'created_by' => $this->leader->id,
            'status' => 'new',
        ]);

        $response = $this->actingAs($this->leader, 'sanctum')
            ->patchJson("/api/sales/marketing-tasks/{$task->id}", [
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
