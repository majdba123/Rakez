<?php

namespace Tests\Feature\AI;

use App\Models\AiCall;
use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiToolsSecurityAndContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_advisor_requires_sales_dashboard_gate_not_only_ai_assistant(): void
    {
        Permission::findOrCreate('use-ai-assistant', 'web');
        Permission::findOrCreate('sales.dashboard.view', 'web');

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');

        $registry = app(ToolRegistry::class);
        $this->assertNotContains('tool_sales_advisor', $registry->allowedToolNamesForUser($user->fresh()));

        $user->givePermissionTo('sales.dashboard.view');
        $this->assertContains('tool_sales_advisor', $registry->allowedToolNamesForUser($user->fresh()));
    }

    public function test_search_records_denies_module_without_domain_permission(): void
    {
        foreach (['use-ai-assistant', 'marketing.tasks.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'marketing.tasks.view']);

        $registry = app(ToolRegistry::class);
        $result = $registry->execute($user, 'tool_search_records', [
            'query' => 'x',
            'modules' => ['leads'],
            'limit' => 5,
        ]);

        $this->assertSame('denied', $result['result']['status'] ?? null);
        $this->assertSame('leads.view', $result['result']['required_permission'] ?? null);
    }

    public function test_ai_call_details_without_lead_requires_leads_view_all(): void
    {
        foreach (['use-ai-assistant', 'ai-calls.manage', 'leads.view'] as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo(['use-ai-assistant', 'ai-calls.manage', 'leads.view']);

        $call = AiCall::create([
            'lead_id' => null,
            'phone_number' => '+966500000000',
            'status' => 'completed',
        ]);

        $registry = app(ToolRegistry::class);
        $denied = $registry->execute($user, 'tool_ai_call_status', [
            'action' => 'call_details',
            'call_id' => $call->id,
        ]);

        $this->assertSame('denied', $denied['result']['status'] ?? null);
        $this->assertSame('leads.view_all', $denied['result']['required_permission'] ?? null);

        Permission::findOrCreate('leads.view_all', 'web');
        $user->givePermissionTo('leads.view_all');

        $ok = $registry->execute($user->fresh(), 'tool_ai_call_status', [
            'action' => 'call_details',
            'call_id' => $call->id,
        ]);

        $this->assertSame('success', $ok['result']['data']['status'] ?? null);
        $this->assertSame($call->id, $ok['result']['data']['call_id'] ?? null);
    }
}
