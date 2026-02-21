<?php

namespace Tests\Unit\Services\Marketing;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingProject;
use App\Services\Marketing\MarketingProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketingProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketingProjectService();
    }

    #[Test]
    public function get_projects_with_completed_contracts_returns_only_approved(): void
    {
        $approved = Contract::factory()->create(['status' => 'approved']);
        MarketingProject::create(['contract_id' => $approved->id]);

        $pending = Contract::factory()->create(['status' => 'pending']);
        MarketingProject::create(['contract_id' => $pending->id]);

        $paginator = $this->service->getProjectsWithCompletedContracts(15);

        $this->assertSame(1, $paginator->total());
        $this->assertSame($approved->id, $paginator->items()[0]->contract_id);
    }

    #[Test]
    public function calculate_campaign_budget_uses_commission_and_duration(): void
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 1_000_000,
            'commission_percent' => 2.5,
            'agreement_duration_days' => 30,
        ]);

        $result = $this->service->calculateCampaignBudget($contract->id, [
            'unit_price' => 1_000_000,
        ]);

        $this->assertSame(25_000.0, $result['commission_value']);
        $this->assertSame(2_500.0, $result['marketing_value']);
        $this->assertGreaterThan(0, $result['daily_budget']);
        $this->assertGreaterThan(0, $result['monthly_budget']);
    }
}
