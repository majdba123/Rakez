<?php

namespace Tests\Unit\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Marketing\DeveloperMarketingPlanService;
use App\Services\Marketing\EmployeeMarketingPlanService;
use App\Services\Marketing\MarketingProjectService;
use App\Services\Marketing\ExpectedSalesService;
use App\Models\ContractInfo;
use Carbon\Carbon;

class BudgetCalculationTest extends TestCase
{
    private DeveloperMarketingPlanService $developerService;
    private EmployeeMarketingPlanService $employeeService;
    private MarketingProjectService $projectService;
    private ExpectedSalesService $salesService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developerService = new DeveloperMarketingPlanService();
        $this->employeeService = new EmployeeMarketingPlanService();
        $this->projectService = new MarketingProjectService();
        $this->salesService = new ExpectedSalesService();
    }

    #[Test]
    public function it_calculates_expected_impressions_correctly()
    {
        $marketingValue = 35000;
        $averageCpm = 25;
        $expected = (35000 / 25) * 1000; // 1,400,000
        
        $result = $this->developerService->calculateExpectedImpressions($marketingValue, $averageCpm);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_calculates_expected_clicks_correctly()
    {
        $marketingValue = 35000;
        $averageCpc = 2.5;
        $expected = 35000 / 2.5; // 14,000
        
        $result = $this->developerService->calculateExpectedClicks($marketingValue, $averageCpc);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_calculates_commission_value_correctly()
    {
        $unitsValue = 1000000;
        $percent = 2.5;
        $expected = 25000;
        
        $result = $this->employeeService->calculateCommissionValue($unitsValue, $percent);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_calculates_marketing_value_correctly()
    {
        $commissionValue = 25000;
        $percent = 10;
        $expected = 2500;
        
        $result = $this->employeeService->calculateMarketingValue($commissionValue, $percent);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_calculates_expected_bookings_correctly()
    {
        $direct = 100;
        $handRaises = 50;
        $conversionRate = 1; // 1%
        $expected = (100 + 50) * 0.01; // 1.5
        
        $result = $this->salesService->calculateExpectedBookings($direct, $handRaises, $conversionRate);
        
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function it_calculates_deposit_value_per_booking_correctly()
    {
        $budget = 35000;
        $expectedBookings = 10;
        $expected = 3500;
        
        $result = $this->salesService->calculateDepositValuePerBooking($budget, $expectedBookings);
        
        $this->assertEquals($expected, $result);
    }
}
