<?php

namespace Tests\Unit\Services\Accounting;

use Tests\TestCase;
use App\Services\Accounting\AccountingDashboardService;
use App\Models\SalesReservation;
use App\Models\Deposit;
use App\Models\Commission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountingDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountingDashboardService();
    }

    /** @test */
    public function it_calculates_units_sold_correctly()
    {
        // Create confirmed reservations
        SalesReservation::factory()->count(3)->create(['status' => 'confirmed', 'confirmed_at' => now()]);
        SalesReservation::factory()->count(2)->create(['status' => 'under_negotiation']);

        $count = $this->service->getUnitsSold();

        $this->assertEquals(3, $count);
    }

    /** @test */
    public function it_calculates_total_received_deposits()
    {
        Deposit::factory()->create(['status' => 'received', 'amount' => 10000]);
        Deposit::factory()->create(['status' => 'confirmed', 'amount' => 15000]);
        Deposit::factory()->create(['status' => 'pending', 'amount' => 5000]); // Should not be counted

        $total = $this->service->getTotalReceivedDeposits();

        $this->assertEquals(25000, $total);
    }

    /** @test */
    public function it_calculates_total_refunded_deposits()
    {
        Deposit::factory()->create(['status' => 'refunded', 'amount' => 5000, 'refunded_at' => now()]);
        Deposit::factory()->create(['status' => 'refunded', 'amount' => 3000, 'refunded_at' => now()]);
        Deposit::factory()->create(['status' => 'received', 'amount' => 10000]); // Should not be counted

        $total = $this->service->getTotalRefundedDeposits();

        $this->assertEquals(8000, $total);
    }

    /** @test */
    public function it_filters_metrics_by_date_range()
    {
        $oldDate = now()->subDays(10);
        $newDate = now();

        SalesReservation::factory()->create(['status' => 'confirmed', 'confirmed_at' => $oldDate]);
        SalesReservation::factory()->create(['status' => 'confirmed', 'confirmed_at' => $newDate]);

        $count = $this->service->getUnitsSold($newDate->toDateString(), $newDate->toDateString());

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_returns_complete_dashboard_metrics()
    {
        $metrics = $this->service->getDashboardMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('units_sold', $metrics);
        $this->assertArrayHasKey('total_received_deposits', $metrics);
        $this->assertArrayHasKey('total_refunded_deposits', $metrics);
        $this->assertArrayHasKey('total_projects_value', $metrics);
        $this->assertArrayHasKey('total_sales_value', $metrics);
        $this->assertArrayHasKey('total_commissions', $metrics);
        $this->assertArrayHasKey('pending_commissions', $metrics);
        $this->assertArrayHasKey('approved_commissions', $metrics);
    }
}
