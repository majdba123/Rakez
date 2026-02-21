<?php

namespace Tests\Unit\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Marketing\MarketingBudgetCalculationService;
use App\Models\MarketingBudgetDistribution;
use App\Models\MarketingProject;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingBudgetCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketingBudgetCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketingBudgetCalculationService();
    }

    #[Test]
    public function it_calculates_platform_budgets_correctly()
    {
        $totalBudget = 35000;
        $platformDistribution = [
            'TikTok' => 20,
            'Meta' => 30,
            'Snapchat' => 20,
            'Google' => 10,
            'X' => 20,
        ];

        $result = $this->service->calculatePlatformBudgets($totalBudget, $platformDistribution);

        $this->assertEquals(7000, $result['TikTok']); // 35000 * 20% = 7000
        $this->assertEquals(10500, $result['Meta']); // 35000 * 30% = 10500
        $this->assertEquals(7000, $result['Snapchat']); // 35000 * 20% = 7000
        $this->assertEquals(3500, $result['Google']); // 35000 * 10% = 3500
        $this->assertEquals(7000, $result['X']); // 35000 * 20% = 7000
    }

    #[Test]
    public function it_calculates_objective_budgets_correctly()
    {
        $platformBudget = 10500;
        $objectives = [
            'impression_percent' => 20,
            'lead_percent' => 50,
            'direct_contact_percent' => 30,
        ];

        $result = $this->service->calculateObjectiveBudgets($platformBudget, $objectives);

        $this->assertEquals(2100, $result['impression']); // 10500 * 20% = 2100
        $this->assertEquals(5250, $result['lead']); // 10500 * 50% = 5250
        $this->assertEquals(3150, $result['direct_contact']); // 10500 * 30% = 3150
    }

    #[Test]
    public function it_calculates_leads_count_correctly()
    {
        $leadsBudget = 5250;
        $cpl = 25;

        $result = $this->service->calculateLeadsCount($leadsBudget, $cpl);

        $this->assertEquals(210, $result); // 5250 / 25 = 210
    }

    #[Test]
    public function it_returns_zero_leads_when_cpl_is_zero()
    {
        $leadsBudget = 5250;
        $cpl = 0;

        $result = $this->service->calculateLeadsCount($leadsBudget, $cpl);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function it_calculates_direct_contacts_count_correctly()
    {
        $directContactBudget = 3150;
        $directContactCost = 35;

        $result = $this->service->calculateDirectContactsCount($directContactBudget, $directContactCost);

        $this->assertEquals(90, $result); // 3150 / 35 = 90
    }

    #[Test]
    public function it_calculates_total_opportunities_correctly()
    {
        $leadsCount = 210;
        $directContactsCount = 90;

        $result = $this->service->calculateTotalOpportunities($leadsCount, $directContactsCount);

        $this->assertEquals(300, $result); // 210 + 90 = 300
    }

    #[Test]
    public function it_calculates_expected_bookings_correctly()
    {
        $totalOpportunities = 300;
        $conversionRate = 3; // 3%

        $result = $this->service->calculateExpectedBookings($totalOpportunities, $conversionRate);

        $this->assertEquals(9, $result); // 300 * 3% = 9
    }

    #[Test]
    public function it_calculates_expected_revenue_correctly()
    {
        $bookingsCount = 9;
        $bookingValue = 2000;

        $result = $this->service->calculateExpectedRevenue($bookingsCount, $bookingValue);

        $this->assertEquals(18000, $result); // 9 * 2000 = 18000
    }

    #[Test]
    public function it_calculates_cost_per_booking_correctly()
    {
        $totalBudget = 35000;
        $bookingsCount = 9;

        $result = $this->service->calculateCostPerBooking($totalBudget, $bookingsCount);

        $this->assertEquals(3888.89, round($result, 2)); // 35000 / 9 = 3888.89
    }

    #[Test]
    public function it_returns_zero_cost_per_booking_when_bookings_is_zero()
    {
        $totalBudget = 35000;
        $bookingsCount = 0;

        $result = $this->service->calculateCostPerBooking($totalBudget, $bookingsCount);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function it_calculates_all_metrics_correctly()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $distribution = MarketingBudgetDistribution::create([
            'marketing_project_id' => $project->id,
            'plan_type' => 'employee',
            'total_budget' => 35000,
            'platform_distribution' => [
                'Meta' => 100, // 100% to Meta for easier testing
            ],
            'platform_objectives' => [
                'Meta' => [
                    'impression_percent' => 20,
                    'lead_percent' => 50,
                    'direct_contact_percent' => 30,
                ],
            ],
            'platform_costs' => [
                'Meta' => [
                    'cpl' => 25,
                    'direct_contact_cost' => 35,
                ],
            ],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
        ]);

        $result = $this->service->calculateAll($distribution);

        // Platform budget
        $this->assertEquals(35000, $result['platform_budgets']['Meta']);

        // Objective budgets
        $this->assertEquals(7000, $result['objective_budgets']['Meta']['impression']);
        $this->assertEquals(17500, $result['objective_budgets']['Meta']['lead']);
        $this->assertEquals(10500, $result['objective_budgets']['Meta']['direct_contact']);

        // Counts
        $this->assertEquals(700, $result['leads_count']['Meta']); // 17500 / 25
        $this->assertEquals(300, $result['direct_contacts_count']['Meta']); // 10500 / 35

        // Totals
        $this->assertEquals(1000, $result['total_opportunities']); // 700 + 300
        $this->assertEquals(30, $result['expected_bookings']); // 1000 * 3%
        $this->assertEquals(60000, $result['expected_revenue']); // 30 * 2000
        $this->assertEquals(1166.67, round($result['cost_per_booking'], 2)); // 35000 / 30
    }

    #[Test]
    public function it_validates_platform_distribution_sum_in_save_or_update()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform distribution percentages must sum to 100%');

        $this->service->saveOrUpdateDistribution($project->id, 'employee', [
            'total_budget' => 35000,
            'platform_distribution' => [
                'Meta' => 50, // Only 50%, should fail
            ],
            'platform_objectives' => [
                'Meta' => [
                    'impression_percent' => 20,
                    'lead_percent' => 50,
                    'direct_contact_percent' => 30,
                ],
            ],
            'platform_costs' => [
                'Meta' => [
                    'cpl' => 25,
                    'direct_contact_cost' => 35,
                ],
            ],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
        ]);
    }

    #[Test]
    public function it_validates_platform_objectives_sum_in_save_or_update()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Platform objectives for Meta must sum to 100%');

        $this->service->saveOrUpdateDistribution($project->id, 'employee', [
            'total_budget' => 35000,
            'platform_distribution' => [
                'Meta' => 100,
            ],
            'platform_objectives' => [
                'Meta' => [
                    'impression_percent' => 20,
                    'lead_percent' => 50,
                    'direct_contact_percent' => 25, // Total = 95%, should fail
                ],
            ],
            'platform_costs' => [
                'Meta' => [
                    'cpl' => 25,
                    'direct_contact_cost' => 35,
                ],
            ],
            'conversion_rate' => 3,
            'average_booking_value' => 2000,
        ]);
    }
}
