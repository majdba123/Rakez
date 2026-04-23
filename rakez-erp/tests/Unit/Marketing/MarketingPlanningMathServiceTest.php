<?php

namespace Tests\Unit\Marketing;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\MarketingSetting;
use App\Services\Marketing\DeveloperMarketingPlanService;
use App\Services\Marketing\EmployeeMarketingPlanService;
use App\Services\Marketing\MarketingBudgetCalculationService;
use App\Services\Marketing\MarketingDistributionBreakdownService;
use App\Services\Marketing\MarketingPlanSuggestionService;
use App\Services\Marketing\MarketingPlanningMathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MarketingPlanningMathServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_average_cost_settings_with_existing_precedence(): void
    {
        MarketingSetting::create(['key' => 'average_cpm', 'value' => '11.25']);
        MarketingSetting::create(['key' => 'default_cpm', 'value' => '99.99']);
        MarketingSetting::create(['key' => 'average_cpc', 'value' => '3.75']);
        MarketingSetting::create(['key' => 'default_cpc', 'value' => '8.25']);

        $service = app(MarketingPlanningMathService::class);

        $this->assertSame(11.25, $service->defaultAverageCpm());
        $this->assertSame(3.75, $service->defaultAverageCpc());
    }

    #[Test]
    public function developer_plan_public_reach_methods_remain_unrounded_wrappers(): void
    {
        $service = app(DeveloperMarketingPlanService::class);

        $this->assertEquals((35000 / 25) * 1000, $service->calculateExpectedImpressions(35000, 25));
        $this->assertEquals(35000 / 2.5, $service->calculateExpectedClicks(35000, 2.5));
        $this->assertSame(0.0, $service->calculateExpectedImpressions(35000, 0));
        $this->assertSame(0.0, $service->calculateExpectedClicks(35000, 0));
    }

    #[Test]
    public function budget_calculation_keeps_rounded_integer_reach_values(): void
    {
        $contract = Contract::factory()->create([
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'agreement_duration_days' => 30,
            'agreement_duration_months' => 1,
        ]);

        $result = app(MarketingBudgetCalculationService::class)->calculateCampaignBudget($contract->id, [
            'unit_price' => 1000000,
            'marketing_percent' => 10,
            'average_cpm' => 3,
            'average_cpc' => 2,
        ]);

        $this->assertSame(833333, $result['expected_impressions']);
        $this->assertSame(1250, $result['expected_clicks']);
    }

    #[Test]
    public function shared_weighted_campaign_distribution_matches_employee_derivation_without_normalizing(): void
    {
        $platformDistribution = [
            'Meta' => 50,
            'TikTok' => 50,
        ];
        $campaignDistributionByPlatform = [
            'Meta' => [
                'Direct Communication' => 0,
                'Hand Raise' => 40,
                'Impression' => 0,
                'Sales' => 60,
            ],
            'TikTok' => [
                'Direct Communication' => 50,
                'Hand Raise' => 0,
                'Impression' => 50,
                'Sales' => 0,
            ],
        ];

        $expected = [
            'Direct Communication' => 25.0,
            'Hand Raise' => 20.0,
            'Impression' => 25.0,
            'Sales' => 30.0,
        ];

        $math = app(MarketingPlanningMathService::class);
        $this->assertEquals($expected, $math->weightedCampaignDistribution(
            $platformDistribution,
            $campaignDistributionByPlatform,
            EmployeeMarketingPlanService::CAMPAIGNS
        ));

        $employeeService = app(EmployeeMarketingPlanService::class);
        $method = new ReflectionMethod($employeeService, 'deriveCampaignDistribution');
        $method->setAccessible(true);

        $this->assertEquals($expected, $method->invoke(
            $employeeService,
            $platformDistribution,
            $campaignDistributionByPlatform
        ));
    }

    #[Test]
    public function suggestion_derivation_still_normalizes_after_shared_weighting(): void
    {
        $service = new MarketingPlanSuggestionService(
            new MarketingDistributionBreakdownService(),
            null,
            app(MarketingPlanningMathService::class)
        );

        $method = new ReflectionMethod($service, 'deriveCampaignDistribution');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            ['Meta' => 50],
            ['Meta' => ['Sales' => 100]]
        );

        $this->assertEquals(100, array_sum($result));
        $this->assertSame(100.0, $result['Sales']);
        $this->assertSame(0.0, $result['Direct Communication']);
        $this->assertSame(0.0, $result['Hand Raise']);
        $this->assertSame(0.0, $result['Impression']);
    }

    #[Test]
    public function employee_campaign_distribution_defaults_remain_equal_per_platform(): void
    {
        $service = app(EmployeeMarketingPlanService::class);
        $method = new ReflectionMethod($service, 'normalizeCampaignDistributionByPlatform');
        $method->setAccessible(true);

        $result = $method->invoke($service, null);

        foreach (EmployeeMarketingPlanService::PLATFORMS as $platform) {
            $this->assertArrayHasKey($platform, $result);
            $this->assertEquals(100, array_sum($result[$platform]));
            $this->assertEqualsCanonicalizing(
                EmployeeMarketingPlanService::CAMPAIGNS,
                array_keys($result[$platform])
            );
        }
    }
}
