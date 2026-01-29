<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingProjectTest extends TestCase
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
    public function it_can_list_marketing_projects()
    {
        $project = Contract::factory()->create(['status' => 'approved']);
        MarketingProject::create(['contract_id' => $project->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_can_show_project_details_with_duration_status()
    {
        $contract = Contract::factory()->create(['status' => 'approved']);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 100,
            'avg_property_value' => 500000,
            'commission_percent' => 2.5
        ]);
        MarketingProject::create(['contract_id' => $contract->id]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'project_name',
                    'duration_status' => ['status', 'label', 'days']
                ]
            ]);
    }

    #[Test]
    public function it_can_calculate_campaign_budget()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 30,
            'avg_property_value' => 1000000,
            'commission_percent' => 2.5
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $contract->id,
                'unit_price' => 1000000
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.commission_value', 25000)
            ->assertJsonPath('data.marketing_value', 2500);
    }
}
