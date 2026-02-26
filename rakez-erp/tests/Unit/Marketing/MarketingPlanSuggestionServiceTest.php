<?php

namespace Tests\Unit\Marketing;

use PHPUnit\Framework\TestCase;
use App\Services\Marketing\MarketingPlanSuggestionService;
use App\Services\Marketing\MarketingDistributionBreakdownService;
use App\Services\Marketing\EmployeeMarketingPlanService;

class MarketingPlanSuggestionServiceTest extends TestCase
{
    private MarketingPlanSuggestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $breakdownService = new MarketingDistributionBreakdownService();
        $this->service = new MarketingPlanSuggestionService($breakdownService);
    }

    public function test_it_suggests_distributions_based_on_goal()
    {
        $inputs = [
            'marketing_value' => 10000,
            'goal' => 'awareness',
            'project_type' => 'on_map'
        ];

        $result = $this->service->suggest($inputs);

        $this->assertArrayHasKey('platform_distribution', $result);
        $this->assertArrayHasKey('campaign_distribution_by_platform', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('rationale', $result);

        // awareness goal favors TikTok and Snapchat
        $this->assertGreaterThan(20, $result['platform_distribution']['TikTok']);
        $this->assertGreaterThan(20, $result['platform_distribution']['Snapchat']);
        
        $this->assertEquals(100, array_sum($result['platform_distribution']));
        
        // Sum of campaigns under Meta should be 100
        $this->assertEquals(100, array_sum($result['campaign_distribution_by_platform']['Meta']));
    }

    public function test_it_adjusts_for_luxury_projects()
    {
        $inputs = [
            'marketing_value' => 50000,
            'goal' => 'leads',
            'project_type' => 'luxury'
        ];

        $result = $this->service->suggest($inputs);

        // Luxury favors LinkedIn and Meta
        $this->assertGreaterThan(10, $result['platform_distribution']['LinkedIn']);
        $this->assertEquals(100, array_sum($result['platform_distribution']));
    }

    public function test_it_generates_correct_breakdown_amounts()
    {
        $inputs = [
            'marketing_value' => 10000,
            'goal' => 'leads',
            'project_type' => 'on_map'
        ];

        $result = $this->service->suggest($inputs);

        $breakdown = $result['breakdown'];
        
        $this->assertEquals(10000, array_sum($breakdown['platform_amounts_sar']));
        
        // Check exact match for each platform's campaigns
        foreach ($result['platform_distribution'] as $platform => $percentage) {
            $platformAmount = $breakdown['platform_amounts_sar'][$platform];
            $campaignAmounts = $breakdown['campaign_amounts_by_platform_sar'][$platform] ?? [];
            
            if ($platformAmount > 0) {
                $this->assertEquals($platformAmount, array_sum($campaignAmounts));
            }
        }
    }
}