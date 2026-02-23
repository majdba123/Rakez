<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Commission;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\Contract;
use App\Models\SecondPartyData;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test commission total amount calculation.
     */
    public function test_calculates_total_commission_correctly(): void
    {
        $commission = new Commission([
            'final_selling_price' => 1000000,
            'commission_percentage' => 2.5,
        ]);

        $commission->calculateTotalAmount();

        $this->assertEquals(25000, $commission->total_amount);
    }

    /**
     * Test VAT calculation (15% of total amount).
     */
    public function test_calculates_vat_correctly(): void
    {
        $commission = new Commission([
            'total_amount' => 25000,
        ]);

        $commission->calculateVAT();

        $this->assertEquals(3750, $commission->vat);
    }

    /**
     * Test net amount calculation after deductions.
     */
    public function test_calculates_net_amount_correctly(): void
    {
        $commission = new Commission([
            'total_amount' => 25000,
            'vat' => 3750,
            'marketing_expenses' => 1000,
            'bank_fees' => 250,
        ]);

        $commission->calculateNetAmount();

        $this->assertEquals(20000, $commission->net_amount);
    }

    /**
     * Test complete commission calculation flow.
     */
    public function test_complete_commission_calculation_flow(): void
    {
        $commission = new Commission([
            'final_selling_price' => 500000,
            'commission_percentage' => 3,
            'marketing_expenses' => 500,
            'bank_fees' => 100,
        ]);

        // Calculate total
        $commission->calculateTotalAmount();
        $this->assertEquals(15000, $commission->total_amount);

        // Calculate VAT
        $commission->calculateVAT();
        $this->assertEquals(2250, $commission->vat);

        // Calculate net
        $commission->calculateNetAmount();
        $this->assertEquals(12150, $commission->net_amount);
    }

    /**
     * Test commission with zero expenses.
     */
    public function test_commission_with_zero_expenses(): void
    {
        $commission = new Commission([
            'final_selling_price' => 1000000,
            'commission_percentage' => 2,
            'marketing_expenses' => 0,
            'bank_fees' => 0,
        ]);

        $commission->calculateTotalAmount();
        $commission->calculateVAT();
        $commission->calculateNetAmount();

        $this->assertEquals(20000, $commission->total_amount);
        $this->assertEquals(3000, $commission->vat);
        $this->assertEquals(17000, $commission->net_amount);
    }

    /**
     * Test commission with high expenses.
     */
    public function test_commission_with_high_expenses(): void
    {
        $commission = new Commission([
            'final_selling_price' => 1000000,
            'commission_percentage' => 2,
            'marketing_expenses' => 5000,
            'bank_fees' => 1000,
        ]);

        $commission->calculateTotalAmount();
        $commission->calculateVAT();
        $commission->calculateNetAmount();

        $this->assertEquals(20000, $commission->total_amount);
        $this->assertEquals(3000, $commission->vat);
        $this->assertEquals(11000, $commission->net_amount);
    }

    /**
     * Test commission status transitions.
     */
    public function test_commission_status_transitions(): void
    {
        $commission = Commission::factory()->create([
            'status' => 'pending',
        ]);

        $this->assertTrue($commission->isPending());
        $this->assertFalse($commission->isApproved());
        $this->assertFalse($commission->isPaid());

        $commission->approve();
        $this->assertTrue($commission->isApproved());
        $this->assertNotNull($commission->approved_at);

        $commission->markAsPaid();
        $this->assertTrue($commission->isPaid());
        $this->assertNotNull($commission->paid_at);
    }

    /**
     * Test commission with fractional percentages.
     */
    public function test_commission_with_fractional_percentage(): void
    {
        $commission = new Commission([
            'final_selling_price' => 750000,
            'commission_percentage' => 2.75,
        ]);

        $commission->calculateTotalAmount();

        $this->assertEquals(20625, $commission->total_amount);
    }

    /**
     * Test commission calculation precision.
     */
    public function test_commission_calculation_precision(): void
    {
        $commission = new Commission([
            'final_selling_price' => 999999.99,
            'commission_percentage' => 2.33,
        ]);

        $commission->calculateTotalAmount();
        $commission->calculateVAT();
        $commission->calculateNetAmount();

        // Verify calculations are numeric and have proper precision
        $this->assertIsNumeric($commission->total_amount);
        $this->assertIsNumeric($commission->vat);
        $this->assertIsNumeric($commission->net_amount);
        
        // Verify the calculations are correct
        $expectedTotal = 23299.9997;
        $this->assertEqualsWithDelta($expectedTotal, $commission->total_amount, 0.01);
    }
}
