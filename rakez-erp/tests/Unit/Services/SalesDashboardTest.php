<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\ContractUnit;
use App\Models\Deposit;
use App\Models\Commission;
use App\Models\Contract;
use App\Models\SalesReservation;
use App\Services\Sales\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SalesDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected SalesAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = new SalesAnalyticsService();
    }

    /**
     * Test getting units sold count.
     */
    public function test_gets_units_sold_count(): void
    {
        ContractUnit::factory()->count(5)->create(['status' => 'sold']);
        ContractUnit::factory()->count(3)->create(['status' => 'available']);

        $count = $this->analyticsService->getUnitsSold();

        $this->assertEquals(5, $count);
    }

    /**
     * Test getting total received deposits.
     */
    public function test_gets_total_received_deposits(): void
    {
        Deposit::factory()->create(['amount' => 5000, 'status' => 'received']);
        Deposit::factory()->create(['amount' => 3000, 'status' => 'confirmed']);
        Deposit::factory()->create(['amount' => 2000, 'status' => 'pending']); // Should not count
        Deposit::factory()->create(['amount' => 1000, 'status' => 'refunded']); // Should not count

        $total = $this->analyticsService->getTotalReceivedDeposits();

        $this->assertEquals(8000, $total);
    }

    /**
     * Test getting total refunded deposits.
     */
    public function test_gets_total_refunded_deposits(): void
    {
        Deposit::factory()->create(['amount' => 2000, 'status' => 'refunded']);
        Deposit::factory()->create(['amount' => 1500, 'status' => 'refunded']);
        Deposit::factory()->create(['amount' => 3000, 'status' => 'received']); // Should not count

        $total = $this->analyticsService->getTotalRefundedDeposits();

        $this->assertEquals(3500, $total);
    }

    /**
     * Test getting total sales value.
     */
    public function test_gets_total_sales_value(): void
    {
        Commission::factory()->create(['final_selling_price' => 1000000]);
        Commission::factory()->create(['final_selling_price' => 750000]);
        Commission::factory()->create(['final_selling_price' => 500000]);

        $total = $this->analyticsService->getTotalSalesValue();

        $this->assertEquals(2250000, $total);
    }

    /**
     * Test getting total commissions.
     */
    public function test_gets_total_commissions(): void
    {
        Commission::factory()->create(['net_amount' => 20000]);
        Commission::factory()->create(['net_amount' => 15000]);
        Commission::factory()->create(['net_amount' => 10000]);

        $total = $this->analyticsService->getTotalCommissions();

        $this->assertEquals(45000, $total);
    }

    /**
     * Test getting pending commissions.
     */
    public function test_gets_pending_commissions(): void
    {
        Commission::factory()->create(['net_amount' => 20000, 'status' => 'pending']);
        Commission::factory()->create(['net_amount' => 15000, 'status' => 'pending']);
        Commission::factory()->create(['net_amount' => 10000, 'status' => 'approved']); // Should not count

        $total = $this->analyticsService->getPendingCommissions();

        $this->assertEquals(35000, $total);
    }

    /**
     * Test getting dashboard KPIs.
     */
    public function test_gets_dashboard_kpis(): void
    {
        // Create test data
        ContractUnit::factory()->count(3)->create(['status' => 'sold']);
        Deposit::factory()->create(['amount' => 5000, 'status' => 'received']);
        Deposit::factory()->create(['amount' => 2000, 'status' => 'refunded']);
        Commission::factory()->create(['final_selling_price' => 1000000, 'net_amount' => 20000]);

        $kpis = $this->analyticsService->getDashboardKPIs();

        $this->assertIsArray($kpis);
        $this->assertArrayHasKey('units_sold', $kpis);
        $this->assertArrayHasKey('total_received_deposits', $kpis);
        $this->assertArrayHasKey('total_refunded_deposits', $kpis);
        $this->assertArrayHasKey('total_sales_value', $kpis);
        $this->assertArrayHasKey('total_commissions', $kpis);
        $this->assertArrayHasKey('pending_commissions', $kpis);

        $this->assertEquals(3, $kpis['units_sold']);
        $this->assertEquals(5000, $kpis['total_received_deposits']);
        $this->assertEquals(2000, $kpis['total_refunded_deposits']);
        $this->assertEquals(1000000, $kpis['total_sales_value']);
    }

    /**
     * Test getting dashboard KPIs with date range.
     */
    public function test_gets_dashboard_kpis_with_date_range(): void
    {
        // Create data with different dates
        $oldCommission = Commission::factory()->create([
            'final_selling_price' => 500000,
            'net_amount' => 10000,
            'created_at' => '2025-12-01',
        ]);

        $newCommission = Commission::factory()->create([
            'final_selling_price' => 1000000,
            'net_amount' => 20000,
            'created_at' => '2026-01-15',
        ]);

        // Get KPIs for January 2026 only
        $kpis = $this->analyticsService->getDashboardKPIs('2026-01-01', '2026-01-31');

        $this->assertEquals(1000000, $kpis['total_sales_value']);
        $this->assertEquals(20000, $kpis['total_commissions']);
    }

    /**
     * Test getting deposit stats by project.
     */
    public function test_gets_deposit_stats_by_project(): void
    {
        $contract = Contract::factory()->create();

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 5000,
            'status' => 'received',
        ]);

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 2000,
            'status' => 'refunded',
        ]);

        $stats = $this->analyticsService->getDepositStatsByProject($contract->id);

        $this->assertEquals(5000, $stats['total_received']);
        $this->assertEquals(2000, $stats['total_refunded']);
        $this->assertEquals(3000, $stats['net_deposits']);
        $this->assertEquals(1, $stats['count_received']);
        $this->assertEquals(1, $stats['count_refunded']);
    }

    /**
     * Test getting commission stats by employee.
     */
    public function test_gets_commission_stats_by_employee(): void
    {
        $user = \App\Models\User::factory()->create();
        $commission = Commission::factory()->create(['net_amount' => 20000]);

        \App\Models\CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user->id,
            'amount' => 5000,
            'status' => 'approved',
        ]);

        \App\Models\CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user->id,
            'amount' => 3000,
            'status' => 'approved',
        ]);

        $stats = $this->analyticsService->getCommissionStatsByEmployee($user->id);

        $this->assertEquals(8000, $stats['total_commission']);
        $this->assertEquals(2, $stats['commission_count']);
        $this->assertEquals(4000, $stats['average_commission']);
    }

    /**
     * Test getting monthly commission report.
     */
    public function test_gets_monthly_commission_report(): void
    {
        $user = \App\Models\User::factory()->create([
            'salary' => 5000,
            'type' => 'sales',
        ]);

        $commission = Commission::factory()->create(['net_amount' => 20000]);

        \App\Models\CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'user_id' => $user->id,
            'amount' => 5000,
            'status' => 'approved',
            'created_at' => '2026-01-15',
        ]);

        $report = $this->analyticsService->getMonthlyCommissionReport(2026, 1);

        $this->assertCount(1, $report);
        $this->assertEquals($user->id, $report[0]->id);
        $this->assertEquals(5000, $report[0]->total_commission);
        $this->assertEquals(1, $report[0]->commission_count);
    }
}
