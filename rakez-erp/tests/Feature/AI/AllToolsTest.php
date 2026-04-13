<?php

namespace Tests\Feature\AI;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;
use App\Services\AI\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AllToolsTest extends TestCase
{
    use RefreshDatabase;

    private ToolRegistry $registry;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ToolRegistry::class);

        // Create all permissions first (Spatie requires them to exist in DB)
        $permissions = [
            'use-ai-assistant',
            'leads.view',
            'leads.view_all',
            'contracts.view',
            'contracts.view_all',
            'sales.dashboard.view',
            'marketing.dashboard.view',
            'marketing.tasks.view',
            'hr.dashboard.view',
            'second_party_data.view',
            'ai-calls.manage',
            'sales.reservations.view',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo($permissions);

        $this->regularUser = User::factory()->create();
    }

    public function test_all_exposed_tools_are_registered(): void
    {
        $names = $this->registry->registeredNames();

        $expected = [
            'tool_search_records',
            'tool_get_lead_summary',
            'tool_get_project_summary',
            'tool_get_contract_status',
            'tool_kpi_sales',
            'tool_explain_access',
            'tool_rag_search',
            'tool_campaign_advisor',
            'tool_hiring_advisor',
            'tool_finance_calculator',
            'tool_marketing_analytics',
            'tool_sales_advisor',
            'tool_ai_call_status',
        ];

        foreach ($expected as $tool) {
            $this->assertContains($tool, $names, "Tool '{$tool}' is not registered.");
        }
    }

    public function test_search_records_tool_permission_denied(): void
    {
        $result = $this->registry->execute($this->regularUser, 'tool_search_records', [
            'query' => 'test',
            'modules' => ['leads'],
        ]);

        $this->assertFalse($result['result']['allowed']);
    }

    public function test_search_records_tool_success(): void
    {
        Lead::factory()->create(['name' => 'Ahmad Test', 'assigned_to' => $this->adminUser->id]);

        $result = $this->registry->execute($this->adminUser, 'tool_search_records', [
            'query' => 'Ahmad',
            'modules' => ['leads'],
            'limit' => 5,
        ]);

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('source_refs', $result);
    }

    public function test_get_lead_summary_tool(): void
    {
        $lead = Lead::factory()->create(['name' => 'Test Lead', 'assigned_to' => $this->adminUser->id]);

        $result = $this->registry->execute($this->adminUser, 'tool_get_lead_summary', [
            'lead_id' => $lead->id,
        ]);

        $this->assertArrayHasKey('data', $result['result']);
        $this->assertEquals($lead->id, $result['result']['data']['id']);
    }

    public function test_get_lead_summary_not_found(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_get_lead_summary', [
            'lead_id' => 99999,
        ]);

        $this->assertSame('invalid_arguments', $result['result']['status'] ?? null);
        $this->assertStringContainsString('not found', $result['result']['error'] ?? '');
    }

    public function test_kpi_sales_tool(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_kpi_sales', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
            'group_by' => null,
        ]);

        $this->assertArrayHasKey('data', $result['result']);
        $this->assertArrayHasKey('total_reservations', $result['result']['data']);
    }

    public function test_finance_calculator_mortgage(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_finance_calculator', [
            'calculation_type' => 'mortgage',
            'unit_price' => 1000000,
            'down_payment_percent' => 10,
            'annual_rate' => 5.5,
            'years' => 25,
        ]);

        $data = $result['result']['data'];
        $this->assertEquals(100000, $data['down_payment']);
        $this->assertEquals(900000, $data['loan_amount']);
        $this->assertGreaterThan(0, $data['monthly_payment']);
    }

    public function test_finance_calculator_romi(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_finance_calculator', [
            'calculation_type' => 'romi',
            'marketing_spend' => 50000,
            'sold_units' => 5,
            'avg_unit_price' => 1000000,
            'commission_rate' => 2.5,
        ]);

        $data = $result['result']['data'];
        $this->assertArrayHasKey('romi_percent', $data);
        $this->assertArrayHasKey('commission_revenue', $data);
    }

    public function test_campaign_advisor_tool(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_campaign_advisor', [
            'budget' => 100000,
            'channel' => 'google',
            'goal' => 'leads',
            'region' => 'الرياض',
        ]);

        $data = $result['result']['data'];
        $this->assertArrayHasKey('estimated_leads', $data);
        $this->assertArrayHasKey('avg_cpl_used', $data);
    }

    public function test_hiring_advisor_tool(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_hiring_advisor', [
            'role' => 'sales',
            'team_size' => 5,
            'project_count' => 3,
        ]);

        $data = $result['result']['data'];
        $this->assertArrayHasKey('profile', $data);
        $this->assertSame('static_knowledge', $data['data_source'] ?? null);
        $this->assertNotEmpty($data['warnings'] ?? []);
    }

    public function test_sales_advisor_reservation_momentum(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_sales_advisor', [
            'topic' => 'reservation_momentum',
            'date_from' => '2020-01-01',
            'date_to' => now()->toDateString(),
            'contract_id' => null,
            'sales_reservation_id' => null,
        ]);

        $data = $result['result']['data'];
        $this->assertSame('success', $data['status'] ?? null);
        $this->assertArrayHasKey('total_reservations', $data);
    }

    public function test_sales_advisor_rejects_legacy_topic(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_sales_advisor', [
            'topic' => 'closing_tips',
            'date_from' => null,
            'date_to' => null,
            'contract_id' => null,
            'sales_reservation_id' => null,
        ]);

        $this->assertSame('unsupported_operation', $result['result']['status'] ?? null);
    }

    public function test_finance_calculator_rejects_unknown_type(): void
    {
        $result = $this->registry->execute($this->adminUser, 'tool_finance_calculator', [
            'calculation_type' => 'not_a_real_type',
            'unit_price' => 1,
            'down_payment_percent' => 10,
            'annual_rate' => 5,
            'years' => 25,
            'sale_price' => 1,
            'commission_rate' => 2,
            'agent_count' => 1,
            'leader_share_percent' => 0,
            'total_units' => 1,
            'avg_unit_price' => 1,
            'sold_units' => 0,
            'marketing_spend' => 0,
            'operational_cost' => 0,
            'installments' => 12,
            'grace_period' => false,
        ]);

        $this->assertSame('unsupported_operation', $result['result']['status'] ?? null);
    }

    public function test_rag_search_tool_with_mock(): void
    {
        $fakeEmbedding = array_fill(0, 1536, 0.1);
        OpenAI::fake([
            EmbeddingResponse::fake([
                'data' => [['object' => 'embedding', 'embedding' => $fakeEmbedding, 'index' => 0]],
            ]),
        ]);

        $result = $this->registry->execute($this->adminUser, 'tool_rag_search', [
            'query' => 'test query',
            'limit' => 5,
            'filters' => null,
        ]);

        $this->assertArrayHasKey('result', $result);
        // No documents seeded, so should return empty matches
        $this->assertSame('insufficient_data', $result['result']['data']['status'] ?? null);
        $this->assertEquals(0, $result['result']['data']['total_found']);
    }

    public function test_unknown_tool_returns_error(): void
    {
        $result = $this->registry->execute($this->adminUser, 'nonexistent_tool', []);

        $this->assertStringContainsString('Unknown tool', $result['result']['error']);
    }
}
