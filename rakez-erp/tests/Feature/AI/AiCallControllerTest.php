<?php

namespace Tests\Feature\AI;

use App\Models\AiCall;
use App\Models\AiCallMessage;
use App\Models\AiCallScript;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

/**
 * Feature tests for AI Call API: initiate, list, show, transcript, retry, scripts CRUD, analytics, bulk.
 */
class AiCallControllerTest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    private User $user;

    private Lead $lead;

    private AiCallScript $script;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai_calling.enabled', true);
        Config::set('ai_calling.call.max_call_attempts', 5);
        Config::set('ai_calling.call.max_concurrent_calls', 10);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->ensurePermission('ai-calls.manage');
        $role->givePermissionTo('ai-calls.manage');

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
        $this->user->givePermissionTo('ai-calls.manage');

        $this->lead = Lead::factory()->create([
            'name' => 'API Test Lead',
            'contact_info' => '+966501234567',
        ]);

        $this->script = AiCallScript::create([
            'name' => 'API Test Script',
            'target_type' => 'lead',
            'language' => 'ar',
            'questions' => [
                ['key' => 'q1', 'text_ar' => 'ما اسمك؟', 'text_en' => 'Name?'],
            ],
            'greeting_text' => 'مرحبا.',
            'closing_text' => 'شكراً.',
            'max_retries_per_question' => 2,
            'is_active' => true,
        ]);
    }

    private function acting(): self
    {
        Sanctum::actingAs($this->user);
        return $this;
    }

    public function test_initiate_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/calls/initiate', [
            'target_id' => $this->lead->id,
            'target_type' => 'lead',
        ]);
        $response->assertUnauthorized();
    }

    public function test_initiate_requires_permission(): void
    {
        $noPerm = User::factory()->create();
        $noPerm->assignRole(Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']));
        Sanctum::actingAs($noPerm);

        $response = $this->postJson('/api/ai/calls/initiate', [
            'target_id' => $this->lead->id,
            'target_type' => 'lead',
        ]);
        $response->assertForbidden();
    }

    public function test_initiate_validates_target_id_and_type(): void
    {
        $this->acting();
        $response = $this->postJson('/api/ai/calls/initiate', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target_id', 'target_type']);
    }

    public function test_initiate_validates_target_type_enum(): void
    {
        $this->acting();
        $response = $this->postJson('/api/ai/calls/initiate', [
            'target_id' => $this->lead->id,
            'target_type' => 'invalid',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target_type']);
    }

    public function test_initiate_creates_call_and_dispatches_job(): void
    {
        Queue::fake();
        $this->acting();

        $response = $this->postJson('/api/ai/calls/initiate', [
            'target_id' => $this->lead->id,
            'target_type' => 'lead',
            'script_id' => $this->script->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('call.status', 'pending');
        $response->assertJsonPath('call.lead_id', $this->lead->id);
        $response->assertJsonPath('call.phone_number', '+966501234567');
        $this->assertDatabaseHas('ai_calls', [
            'lead_id' => $this->lead->id,
            'status' => 'pending',
        ]);
        Queue::assertPushed(\App\Jobs\InitiateAiCallJob::class);
    }

    public function test_initiate_fails_when_ai_calling_disabled(): void
    {
        Config::set('ai_calling.enabled', false);
        $this->acting();

        $response = $this->postJson('/api/ai/calls/initiate', [
            'target_id' => $this->lead->id,
            'target_type' => 'lead',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'فشل في إنشاء المكالمة']);
    }

    public function test_index_returns_paginated_calls(): void
    {
        AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'completed',
            'initiated_by' => $this->user->id,
        ]);
        $this->acting();

        $response = $this->getJson('/api/ai/calls');
        $response->assertOk();
        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_index_can_filter_by_status(): void
    {
        $this->acting();
        $response = $this->getJson('/api/ai/calls?status=completed');
        $response->assertOk();
    }

    public function test_show_returns_call_details(): void
    {
        $call = AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'customer_name' => 'Test',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'completed',
            'initiated_by' => $this->user->id,
        ]);
        $this->acting();

        $response = $this->getJson("/api/ai/calls/{$call->id}");
        $response->assertOk();
        $response->assertJsonPath('data.id', $call->id);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonStructure(['data' => ['messages']]);
    }

    public function test_show_returns_404_for_missing_call(): void
    {
        $this->acting();
        $response = $this->getJson('/api/ai/calls/99999');
        $response->assertNotFound();
    }

    public function test_transcript_returns_messages(): void
    {
        $call = AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'completed',
            'initiated_by' => $this->user->id,
        ]);
        AiCallMessage::create([
            'ai_call_id' => $call->id,
            'role' => 'ai',
            'content' => 'مرحبا',
            'question_key' => 'q1',
            'timestamp_in_call' => 0,
        ]);
        $this->acting();

        $response = $this->getJson("/api/ai/calls/{$call->id}/transcript");
        $response->assertOk();
        $response->assertJsonStructure(['data' => ['call', 'messages']]);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.messages')));
    }

    public function test_retry_creates_new_call_for_failed(): void
    {
        Queue::fake();
        $failed = AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'failed',
            'attempt_number' => 1,
            'initiated_by' => $this->user->id,
        ]);
        $this->acting();

        $response = $this->postJson("/api/ai/calls/{$failed->id}/retry");
        $response->assertStatus(201);
        $response->assertJsonPath('call.status', 'pending');
        $response->assertJsonPath('call.attempt_number', 2);
        $this->assertDatabaseCount('ai_calls', 2);
        Queue::assertPushed(\App\Jobs\InitiateAiCallJob::class);
    }

    public function test_retry_fails_for_completed_call(): void
    {
        $completed = AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'completed',
            'initiated_by' => $this->user->id,
        ]);
        $this->acting();

        $response = $this->postJson("/api/ai/calls/{$completed->id}/retry");
        $response->assertStatus(422);
    }

    public function test_scripts_list_returns_scripts(): void
    {
        $this->acting();
        $response = $this->getJson('/api/ai/calls/scripts');
        $response->assertOk();
        $response->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_store_script_creates_script(): void
    {
        $this->acting();
        $response = $this->postJson('/api/ai/calls/scripts', [
            'name' => 'New Script',
            'target_type' => 'lead',
            'questions' => [
                ['key' => 'q1', 'text_ar' => 'سؤال', 'text_en' => 'Question'],
            ],
            'greeting_text' => 'أهلاً',
            'closing_text' => 'وداعاً',
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'New Script');
        $this->assertDatabaseHas('ai_call_scripts', ['name' => 'New Script']);
    }

    public function test_update_script_updates_script(): void
    {
        $this->acting();
        $response = $this->putJson("/api/ai/calls/scripts/{$this->script->id}", [
            'name' => 'Updated Name',
            'is_active' => true,
        ]);
        $response->assertOk();
        $this->script->refresh();
        $this->assertSame('Updated Name', $this->script->name);
    }

    public function test_delete_script_deactivates_script(): void
    {
        $this->acting();
        $response = $this->deleteJson("/api/ai/calls/scripts/{$this->script->id}");
        $response->assertOk();
        $this->script->refresh();
        $this->assertFalse($this->script->is_active);
    }

    public function test_delete_script_fails_when_active_calls_exist(): void
    {
        AiCall::create([
            'lead_id' => $this->lead->id,
            'customer_type' => 'lead',
            'phone_number' => '+966501234567',
            'script_id' => $this->script->id,
            'status' => 'in_progress',
            'initiated_by' => $this->user->id,
        ]);
        $this->acting();

        $response = $this->deleteJson("/api/ai/calls/scripts/{$this->script->id}");
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'لا يمكن حذف السكريبت لأن فيه مكالمات نشطة تستخدمه']);
    }

    public function test_analytics_returns_structure(): void
    {
        $this->acting();
        $response = $this->getJson('/api/ai/calls/analytics');
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total_calls',
                'completed',
                'failed',
                'no_answer',
                'success_rate',
                'avg_duration_seconds',
                'by_status',
                'daily_calls',
            ],
        ]);
    }

    public function test_bulk_initiate_validates_and_queues_calls(): void
    {
        Queue::fake();
        $lead2 = Lead::factory()->create(['name' => 'Lead 2', 'contact_info' => '+966509876543']);
        $this->acting();

        $response = $this->postJson('/api/ai/calls/bulk', [
            'target_ids' => [$this->lead->id, $lead2->id],
            'target_type' => 'lead',
            'script_id' => $this->script->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('queued', 2);
        $this->assertDatabaseCount('ai_calls', 2);
        Queue::assertPushed(\App\Jobs\InitiateAiCallJob::class, 2);
    }

    public function test_bulk_initiate_validates_script_required(): void
    {
        $this->acting();
        $response = $this->postJson('/api/ai/calls/bulk', [
            'target_ids' => [$this->lead->id],
            'target_type' => 'lead',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['script_id']);
    }
}
