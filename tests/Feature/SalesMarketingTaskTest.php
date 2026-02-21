<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MarketingTask;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesMarketingTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_marketing_user_can_list_daily_tasks()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        MarketingTask::factory()->count(3)->create([
            'marketer_id' => $user->id
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/marketing/tasks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'task_name', 'status']
                ]
            ]);
    }

    public function test_marketing_user_can_create_task()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $contract = Contract::factory()->create();
        $marketer = User::factory()->create(['type' => 'marketing']);

        $taskData = [
            'contract_id' => $contract->id,
            'task_name' => 'Create Facebook Campaign',
            'marketer_id' => $marketer->id,
            'design_description' => 'Launch new campaign'
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/marketing/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Marketing task created successfully'
            ]);

        $this->assertDatabaseHas('marketing_tasks', [
            'task_name' => 'Create Facebook Campaign'
        ]);
    }

    public function test_marketing_user_can_update_task_status()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $task = MarketingTask::factory()->create([
            'status' => 'new'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/marketing/tasks/{$task->id}/status", [
                'status' => 'completed'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task status updated successfully'
            ]);

        $this->assertDatabaseHas('marketing_tasks', [
            'id' => $task->id,
            'status' => 'completed'
        ]);
    }

    public function test_task_status_validation()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        $task = MarketingTask::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/marketing/tasks/{$task->id}/status", [
                'status' => 'invalid_status'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_can_filter_tasks_by_status()
    {
        $user = User::factory()->create(['type' => 'marketing']);
        $user->syncRoles(['marketing']);

        MarketingTask::factory()->create([
            'marketer_id' => $user->id,
            'status' => 'new'
        ]);

        MarketingTask::factory()->create([
            'marketer_id' => $user->id,
            'status' => 'completed'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/marketing/tasks?status=new");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
