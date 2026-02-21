<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $assignee;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create(['name' => 'Test Team']);
        $this->user = User::factory()->create(['name' => 'Creator']);
        $this->assignee = User::factory()->create(['name' => 'Assignee']);
    }

    #[Test]
    public function store_creates_task_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tasks', [
                'task_name' => 'مراجعة التقرير',
                'team_id' => $this->team->id,
                'due_at' => now()->addDay()->toIso8601String(),
                'assigned_to' => $this->assignee->id,
                'status' => 'in_progress',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ المهمة بنجاح',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'task_name',
                    'team_id',
                    'team_name',
                    'due_at',
                    'assigned_to',
                    'assignee_name',
                    'status',
                    'status_label_ar',
                    'created_by',
                    'creator_name',
                    'created_at',
                ],
            ]);
        $response->assertJsonPath('data.task_name', 'مراجعة التقرير');
        $response->assertJsonPath('data.assigned_to', $this->assignee->id);
        $response->assertJsonPath('data.created_by', $this->user->id);

        $this->assertDatabaseHas('tasks', [
            'task_name' => 'مراجعة التقرير',
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function store_returns_401_when_unauthenticated(): void
    {
        $response = $this->postJson('/api/tasks', [
            'task_name' => 'Task',
            'team_id' => $this->team->id,
            'due_at' => now()->addDay()->toIso8601String(),
            'assigned_to' => $this->assignee->id,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function store_returns_422_when_required_fields_missing(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tasks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_name', 'team_id', 'due_at', 'assigned_to']);
    }

    #[Test]
    public function store_returns_422_when_team_id_invalid(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tasks', [
                'task_name' => 'Task',
                'team_id' => 99999,
                'due_at' => now()->addDay()->toIso8601String(),
                'assigned_to' => $this->assignee->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);
    }

    #[Test]
    public function store_returns_422_when_status_invalid(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/tasks', [
                'task_name' => 'Task',
                'team_id' => $this->team->id,
                'due_at' => now()->addDay()->toIso8601String(),
                'assigned_to' => $this->assignee->id,
                'status' => 'invalid_status',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function my_tasks_returns_only_assigned_tasks_and_200(): void
    {
        Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'task_name' => 'My Task',
        ]);
        Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->user->id,
            'created_by' => $this->assignee->id,
            'task_name' => 'Other Task',
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->getJson('/api/my-tasks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم جلب قائمة المهام بنجاح',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_name',
                        'team_name',
                        'creator_name',
                        'created_at',
                        'due_at',
                        'status',
                        'status_label_ar',
                    ],
                ],
                'meta' => [
                    'pagination' => [
                        'total',
                        'per_page',
                        'current_page',
                        'total_pages',
                    ],
                ],
            ]);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.task_name', 'My Task');
    }

    #[Test]
    public function my_tasks_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/my-tasks');
        $response->assertStatus(401);
    }

    #[Test]
    public function my_tasks_can_filter_by_status(): void
    {
        Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        Task::factory()->completed()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->getJson('/api/my-tasks?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');
    }

    #[Test]
    public function update_status_allows_assignee_to_update_and_returns_200(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'status' => Task::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson("/api/my-tasks/{$task->id}/status", [
                'status' => Task::STATUS_COMPLETED,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم تحديث الحالة بنجاح',
                'data' => ['status' => 'completed'],
            ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function update_status_requires_reason_when_could_not_complete(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson("/api/my-tasks/{$task->id}/status", [
                'status' => Task::STATUS_COULD_NOT_COMPLETE,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cannot_complete_reason']);
    }

    #[Test]
    public function update_status_accepts_could_not_complete_with_reason(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson("/api/my-tasks/{$task->id}/status", [
                'status' => Task::STATUS_COULD_NOT_COMPLETE,
                'cannot_complete_reason' => 'الموارد غير متوفرة',
            ]);

        $response->assertStatus(200);
        $task->refresh();
        $this->assertSame('could_not_complete', $task->status);
        $this->assertSame('الموارد غير متوفرة', $task->cannot_complete_reason);
    }

    #[Test]
    public function update_status_returns_403_when_user_is_not_assignee(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/my-tasks/{$task->id}/status", [
                'status' => Task::STATUS_COMPLETED,
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
    }

    #[Test]
    public function update_status_returns_404_when_task_not_found(): void
    {
        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson('/api/my-tasks/99999/status', [
                'status' => Task::STATUS_COMPLETED,
            ]);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function update_status_returns_422_when_status_invalid(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson("/api/my-tasks/{$task->id}/status", [
                'status' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function hr_alias_store_creates_task_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/hr/tasks', [
                'task_name' => 'HR alias task',
                'team_id' => $this->team->id,
                'due_at' => now()->addDay()->toIso8601String(),
                'assigned_to' => $this->assignee->id,
                'status' => 'in_progress',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'تم حفظ المهمة بنجاح',
            ])
            ->assertJsonPath('data.task_name', 'HR alias task');
        $this->assertDatabaseHas('tasks', [
            'task_name' => 'HR alias task',
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function hr_alias_my_tasks_returns_only_assigned_tasks_and_200(): void
    {
        Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'task_name' => 'My HR alias task',
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->getJson('/api/hr/my-tasks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم جلب قائمة المهام بنجاح',
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.task_name', 'My HR alias task');
    }

    #[Test]
    public function hr_alias_update_status_returns_200(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'assigned_to' => $this->assignee->id,
            'created_by' => $this->user->id,
            'status' => Task::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->assignee, 'sanctum')
            ->patchJson("/api/hr/my-tasks/{$task->id}/status", [
                'status' => Task::STATUS_COMPLETED,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'تم تحديث الحالة بنجاح',
                'data' => ['status' => 'completed'],
            ]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }
}
