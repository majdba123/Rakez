<?php

namespace Tests\Integration\AI;

use App\Models\AIConversation;
use App\Models\AiAuditEntry;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;

class AIApiRealEndpointsAccessAndSecurityTest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie caches can survive within the same PHP process across tests.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function authHeader(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_access_ai_assistant_chat_requires_authentication(): void
    {
        $before = AiAuditEntry::count();

        $response = $this->postJson('/api/ai/assistant/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
        $this->assertSame($before, AiAuditEntry::count(), 'No AI audit entries should be written when auth fails');
    }

    public function test_access_ai_assistant_chat_rejects_user_without_use_ai_assistant_permission(): void
    {
        // Intentionally no roles/permissions to ensure can('use-ai-assistant') is false.
        $user = User::factory()->create();
        $this->assertFalse($user->can('use-ai-assistant'), 'Test precondition failed: user unexpectedly has use-ai-assistant capability');

        $response = $this->postJson('/api/ai/assistant/chat', [
            'message' => 'كيف اسوي عقد؟',
        ], $this->authHeader($user));

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'You do not have permission to use the AI assistant.',
        ]);

        $toolCalls = AiAuditEntry::where('action', 'tool_call')->count();
        $this->assertSame(0, $toolCalls, 'No tool calls should happen on permission denial');
    }

    public function test_role_based_ai_calls_route_blocks_non_allowed_roles_even_if_permission_granted(): void
    {
        $user = User::factory()->create(['type' => 'accounting']);
        Role::firstOrCreate(['name' => 'accounting', 'guard_name' => 'web']);
        $user->assignRole('accounting');

        $this->ensurePermission('ai-calls.manage');
        $user->givePermissionTo('ai-calls.manage');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/ai/calls');
        $response->assertStatus(403);

        $this->assertSame(0, AiAuditEntry::count(), 'Role denial should occur before any AI work');
    }

    public function test_role_based_ai_knowledge_route_allows_admin_only(): void
    {
        $admin = User::factory()->create(['type' => 'admin']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/ai/knowledge');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));

        $nonAdmin = User::factory()->create(['type' => 'sales']);
        Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $nonAdmin->assignRole('sales');
        $this->assertFalse($nonAdmin->hasRole('admin'), 'Test precondition failed: non-admin user unexpectedly has admin role');

        Sanctum::actingAs($nonAdmin);
        $response2 = $this->getJson('/api/ai/knowledge');
        $response2->assertStatus(403);
    }

    public function test_v2_tools_chat_validates_missing_message_field_before_ai_execution(): void
    {
        $user = $this->createUserWithPermissions(['use-ai-assistant']);

        $response = $this->withHeaders($this->authHeader($user))->postJson('/api/ai/tools/chat', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);

        $this->assertSame(0, AiAuditEntry::count(), 'Validation must fail before any AI execution');
        $this->assertSame(0, AIConversation::count(), 'No conversation rows should be created on validation failure');
    }

    public function test_section_restriction_blocks_marketing_section_for_users_without_required_capability(): void
    {
        $user = $this->createUserWithPermissions(['use-ai-assistant']);

        $response = $this->withHeaders($this->authHeader($user))->postJson('/api/ai/ask', [
            'question' => 'ما هي آخر أخبار التسويق؟',
            'section' => 'marketing_dashboard',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'UNAUTHORIZED_SECTION',
        ]);

        $this->assertSame(0, AIConversation::count(), 'Section denial must happen before starting any conversation');
        $toolCalls = AiAuditEntry::where('action', 'tool_call')->count();
        $this->assertSame(0, $toolCalls, 'No tool calls should happen when section is denied');
    }

    public function test_budget_exceeded_returns_429_and_does_not_call_ai(): void
    {
        config(['ai_assistant.budgets.per_user_daily_tokens' => 1]);

        $user = $this->createUserWithPermissions(['use-ai-assistant']);

        AIConversation::create([
            'user_id' => $user->id,
            'session_id' => 'budget_session_existing',
            'role' => 'user',
            'message' => 'previous content',
            'section' => null,
            'metadata' => [],
            'model' => 'test',
            'prompt_tokens' => 1,
            'completion_tokens' => 0,
            'total_tokens' => 1,
            'latency_ms' => 0,
            'request_id' => 'req_existing',
            'is_summary' => false,
        ]);

        $response = $this->withHeaders($this->authHeader($user))->postJson('/api/ai/ask', [
            'question' => 'سؤال جديد',
            'section' => 'general',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'error_code' => 'ai_budget_exceeded',
        ]);

        $this->assertSame(1, AIConversation::count());
        $toolCalls = AiAuditEntry::where('action', 'tool_call')->count();
        $this->assertSame(0, $toolCalls, 'No tool calls should happen when budget is exceeded');
    }

    public function test_access_explanation_ownership_mismatch_does_not_trigger_tool_calls(): void
    {
        // Ownership mismatch on reservations is deterministic (policy checks marketing_employee_id).
        $requester = $this->createUserWithPermissions(['sales.reservations.view']);
        $other = User::factory()->create();

        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $other->id,
        ]);

        $beforeToolCalls = AiAuditEntry::where('action', 'tool_call')->count();

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/ai/ask', [
            'question' => "Why can't I see reservation {$reservation->id}?",
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.access_summary.allowed', false);
        $response->assertJsonPath('data.access_summary.reason_code', 'ownership_mismatch');

        $afterToolCalls = AiAuditEntry::where('action', 'tool_call')->count();
        $this->assertSame($beforeToolCalls, $afterToolCalls, 'Access explanation path should not invoke any tools');
    }
}

