<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\MarketingTask;
use App\Models\UserNotification;
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
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $this->marketingUser->id,
            'status' => 'pending',
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
<<<<<<< HEAD
<<<<<<< HEAD

    #[Test]
    public function it_returns_422_when_required_fields_are_missing()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/tasks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contract_id', 'task_name', 'marketer_id']);
    }

    #[Test]
    public function it_returns_422_with_proper_error_messages()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/tasks', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'contract_id',
                    'task_name',
                    'marketer_id'
                ]
            ])
            ->assertJsonValidationErrors(['contract_id', 'task_name', 'marketer_id']);
        
        // Verify message contains at least one of the required field names
        $message = $response->json('message');
        $this->assertTrue(
            str_contains(strtolower($message), 'contract id') || 
            str_contains(strtolower($message), 'task name') || 
            str_contains(strtolower($message), 'marketer id'),
            "Message should mention at least one required field. Got: {$message}"
        );
    }

    #[Test]
    public function it_validates_contract_id_exists()
    {
        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/tasks', [
                'contract_id' => 99999,
                'task_name' => 'Test Task',
                'marketer_id' => $this->marketingUser->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contract_id']);
    }

    #[Test]
    public function it_validates_marketer_id_exists()
    {
        $contract = Contract::factory()->create();

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/tasks', [
                'contract_id' => $contract->id,
                'task_name' => 'Test Task',
                'marketer_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['marketer_id']);
    }
=======
>>>>>>> parent of 29c197a (Add edits)
=======
>>>>>>> parent of 29c197a (Add edits)
}
