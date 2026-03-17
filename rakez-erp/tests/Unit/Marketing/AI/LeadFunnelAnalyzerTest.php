<?php

namespace Tests\Unit\Marketing\AI;

use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\SalesReservation;
use App\Services\Marketing\AI\CampaignPerformanceAggregator;
use App\Services\Marketing\AI\LeadFunnelAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadFunnelAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private LeadFunnelAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new LeadFunnelAnalyzer(new CampaignPerformanceAggregator);
    }

    public function test_build_funnel_returns_stages_from_ads_and_leads(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-31',
            'breakdown_hash' => 'none',
            'impressions' => 10000,
            'clicks' => 500,
            'spend' => 200.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 8000,
        ]);

        $lead1 = Lead::create([
            'name' => 'Lead 1',
            'contact_info' => 'lead@test.com',
            'source' => 'ads_meta',
            'campaign_platform' => 'meta',
            'status' => 'new',
            'project_id' => null,
        ]);
        $lead1->created_at = '2026-01-15';
        $lead1->save();
        $lead2 = Lead::create([
            'name' => 'Lead 2',
            'contact_info' => 'lead2@test.com',
            'source' => 'ads_meta',
            'campaign_platform' => 'meta',
            'status' => 'contacted',
            'project_id' => null,
        ]);
        $lead2->created_at = '2026-01-16';
        $lead2->save();

        $result = $this->analyzer->buildFunnel('meta', '2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('funnel', $result);
        $funnel = $result['funnel'];
        $this->assertSame(200.0, $funnel['spend']);
        $this->assertSame(10000, $funnel['impressions']);
        $this->assertSame(500, $funnel['clicks']);
        $this->assertSame(2, $funnel['leads']);
        $this->assertSame(1, $funnel['contacted']); // contacted + qualified + converted
        $this->assertSame(0, $funnel['qualified']);  // qualified + converted only
        $this->assertArrayHasKey('dropoff_rates', $result);
        $this->assertArrayHasKey('bottleneck', $result);
        $this->assertSame(100.0, $result['cost_per_lead']);
    }

    public function test_build_funnel_with_project_id_filters_leads(): void
    {
        $contract = Contract::factory()->create();
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-31',
            'breakdown_hash' => 'none',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 50.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 4000,
        ]);
        $lead = Lead::create([
            'name' => 'P1 Lead',
            'contact_info' => 'p1@test.com',
            'source' => 'ads_meta',
            'campaign_platform' => 'meta',
            'status' => 'qualified',
            'project_id' => $contract->id,
        ]);
        $lead->created_at = '2026-01-10';
        $lead->save();

        $result = $this->analyzer->buildFunnel('meta', '2026-01-01', '2026-01-31', $contract->id);

        $this->assertSame(1, $result['funnel']['leads']);
        $this->assertSame(1, $result['funnel']['qualified']);
    }

    public function test_compare_platforms_returns_per_platform_funnel(): void
    {
        AdsInsightRow::create([
            'platform' => 'meta',
            'account_id' => 'a1',
            'level' => 'campaign',
            'entity_id' => 'c1',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-31',
            'breakdown_hash' => 'none',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 25.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 800,
        ]);
        AdsInsightRow::create([
            'platform' => 'tiktok',
            'account_id' => 'a2',
            'level' => 'campaign',
            'entity_id' => 'c2',
            'date_start' => '2026-01-01',
            'date_stop' => '2026-01-31',
            'breakdown_hash' => 'none',
            'impressions' => 2000,
            'clicks' => 100,
            'spend' => 40.0,
            'conversions' => 0,
            'revenue' => 0,
            'reach' => 1500,
        ]);

        $result = $this->analyzer->comparePlatforms('2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('platforms', $result);
        $this->assertArrayHasKey('meta', $result['platforms']);
        $this->assertArrayHasKey('snap', $result['platforms']);
        $this->assertArrayHasKey('tiktok', $result['platforms']);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertSame(25.0, $result['platforms']['meta']['funnel']['spend']);
        $this->assertSame(40.0, $result['platforms']['tiktok']['funnel']['spend']);
    }

    public function test_by_project_type_segments_by_contract_is_off_plan(): void
    {
        $contractOnMap = Contract::factory()->create(['is_off_plan' => true]);
        $contractReady = Contract::factory()->create(['is_off_plan' => false]);

        $l1 = Lead::create([
            'name' => 'On Map',
            'contact_info' => 'on@test.com',
            'source' => 'web',
            'campaign_platform' => null,
            'status' => 'new',
            'project_id' => $contractOnMap->id,
        ]);
        $l1->created_at = '2026-01-10';
        $l1->save();
        $l2 = Lead::create([
            'name' => 'Ready 1',
            'contact_info' => 'r1@test.com',
            'source' => 'web',
            'campaign_platform' => null,
            'status' => 'new',
            'project_id' => $contractReady->id,
        ]);
        $l2->created_at = '2026-01-10';
        $l2->save();
        $l3 = Lead::create([
            'name' => 'Ready 2',
            'contact_info' => 'r2@test.com',
            'source' => 'web',
            'campaign_platform' => null,
            'status' => 'new',
            'project_id' => $contractReady->id,
        ]);
        $l3->created_at = '2026-01-11';
        $l3->save();

        $result = $this->analyzer->byProjectType('2026-01-01', '2026-01-31');

        $this->assertArrayHasKey('on_map', $result);
        $this->assertArrayHasKey('ready', $result);
        $this->assertSame(1, $result['on_map']['leads']);
        $this->assertSame(2, $result['ready']['leads']);
    }
}
