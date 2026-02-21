<?php

namespace Tests\Feature\Marketing;

use Tests\Feature\Auth\BasePermissionTestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Models\ProjectMedia;
use App\Models\SecondPartyData;

class MarketingProjectTest extends BasePermissionTestCase
{
    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingUser = $this->createMarketingStaff();
    }

    #[Test]
    public function it_can_list_marketing_projects()
    {
        $project = Contract::factory()->create(['status' => 'approved']);
        SecondPartyData::factory()->create(['contract_id' => $project->id]);
        ContractInfo::factory()->create([
            'contract_id' => $project->id,
            'avg_property_value' => 500000,
            'commission_percent' => 2.5,
            'agency_number' => 'ADV-555'
        ]);
        MarketingProject::create(['contract_id' => $project->id]);
        $now = now();
        ProjectMedia::create([
            'contract_id' => $project->id,
            'type' => 'image',
            'url' => 'https://cdn.example.com/photo-1.jpg',
            'department' => 'photography',
            'approved_at' => $now,
        ]);
        ProjectMedia::create([
            'contract_id' => $project->id,
            'type' => 'video',
            'url' => 'https://cdn.example.com/video-1.mp4',
            'department' => 'montage',
            'approved_at' => $now,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'contract_id',
                        'project_name',
                        'developer_name',
                        'units_count' => ['available', 'pending'],
                        'avg_unit_price',
                        'advertiser_number',
                        'advertiser_number_value',
                        'advertiser_number_status',
                        'commission_percent',
                        'total_available_value',
                        'media_links',
                        'description',
                    ]
                ]
            ]);

        $this->assertEquals('ADV-555', $response->json('data.0.advertiser_number_value'));
        $this->assertEquals('Available', $response->json('data.0.advertiser_number_status'));
        $this->assertCount(2, $response->json('data.0.media_links'));
        $this->assertCount(1, $response->json('data'));
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
            'agreement_duration_months' => 1,
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

    #[Test]
    public function it_uses_contract_duration_months_for_monthly_budget_when_available()
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 60,
            'agreement_duration_months' => 2,
            'avg_property_value' => 1000000,
            'commission_percent' => 2.5
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->postJson('/api/marketing/projects/calculate-budget', [
                'contract_id' => $contract->id,
                'unit_price' => 1000000
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.monthly_budget', 1250);
    }
}
