<?php

namespace Tests\Unit\Marketing;

use PHPUnit\Framework\TestCase;
use App\Services\Marketing\MarketingDistributionBreakdownService;

class MarketingDistributionBreakdownServiceTest extends TestCase
{
    private MarketingDistributionBreakdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketingDistributionBreakdownService();
    }

    public function test_it_distributes_platform_amounts_exactly()
    {
        $marketingValue = 1000;
        $distribution = [
            'Meta' => 33.33,
            'TikTok' => 33.33,
            'Snapchat' => 33.34,
        ];

        $result = $this->service->breakdownPlatforms($marketingValue, $distribution);

        $this->assertEquals(333, $result['amounts']['Meta']);
        $this->assertEquals(333, $result['amounts']['TikTok']);
        $this->assertEquals(334, $result['amounts']['Snapchat']);
        
        $this->assertEquals(1000, array_sum($result['amounts']));
    }

    public function test_it_handles_rounding_adjustments_correctly()
    {
        // 1000 * 30% = 300
        // 1000 * 30% = 300
        // 1000 * 40% = 400
        // sum = 1000
        
        // Let's test an amount that doesn't split nicely
        $marketingValue = 100;
        $distribution = [
            'Meta' => 33,     // 33
            'TikTok' => 33,   // 33
            'Snapchat' => 34, // 34
        ];
        
        $result = $this->service->breakdownPlatforms($marketingValue, $distribution);
        $this->assertEquals(100, array_sum($result['amounts']));
        
        // Another case
        $marketingValue = 10;
        $distribution = [
            'Meta' => 33.33,     
            'TikTok' => 33.33,   
            'Snapchat' => 33.34, 
        ];
        
        $result = $this->service->breakdownPlatforms($marketingValue, $distribution);
        $this->assertEquals(10, array_sum($result['amounts']));
    }
    
    public function test_it_returns_zero_when_percentages_are_zero()
    {
        $marketingValue = 1000;
        $distribution = [
            'Meta' => 0,
            'TikTok' => 0,
        ];

        $result = $this->service->breakdownPlatforms($marketingValue, $distribution);

        $this->assertEquals(0, $result['amounts']['Meta']);
        $this->assertEquals(0, $result['amounts']['TikTok']);
        $this->assertEquals(0, array_sum($result['amounts']));
    }
}