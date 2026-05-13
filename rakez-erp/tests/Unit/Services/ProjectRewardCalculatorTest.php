<?php

namespace Tests\Unit\Services;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ProjectRewardSetting;
use App\Models\SalesReservation;
use App\Services\Accounting\ProjectRewardCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRewardCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected ProjectRewardCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ProjectRewardCalculator();
    }

    public function test_manual_without_vat(): void
    {
        $reservation = SalesReservation::factory()->create();

        $base = $this->calculator->calculateBaseAmount(
            ProjectRewardSetting::MODE_MANUAL_AMOUNT,
            50000,
            null,
            $reservation,
        );
        $vat = $this->calculator->calculateVat((float) $base['base_amount'], false, 15);

        $this->assertSame(50000.0, (float) $base['base_amount']);
        $this->assertSame(0.0, (float) $vat['vat_amount']);
        $this->assertSame(50000.0, (float) $vat['total_amount']);
        $this->assertSame(50000.0, (float) $vat['distribution_pool_amount']);
    }

    public function test_manual_with_vat(): void
    {
        $reservation = SalesReservation::factory()->create();

        $base = $this->calculator->calculateBaseAmount(
            ProjectRewardSetting::MODE_MANUAL_AMOUNT,
            50000,
            null,
            $reservation,
        );
        $vat = $this->calculator->calculateVat((float) $base['base_amount'], true, 15);

        $this->assertSame(7500.0, (float) $vat['vat_amount']);
        $this->assertSame(57500.0, (float) $vat['total_amount']);
        $this->assertSame(50000.0, (float) $vat['distribution_pool_amount']);
    }

    public function test_percentage_of_sale_uses_reservation_proposed_price(): void
    {
        $reservation = SalesReservation::factory()->create([
            'proposed_price' => 1000000,
        ]);

        $base = $this->calculator->calculateBaseAmount(
            ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE,
            null,
            5,
            $reservation,
        );

        $this->assertSame(1000000.0, (float) $base['calculation_base_amount']);
        $this->assertSame(50000.0, (float) $base['base_amount']);
    }

    public function test_percentage_of_sale_falls_back_to_contract_unit_price(): void
    {
        $contract = Contract::factory()->create();
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'price' => 800000,
        ]);
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'proposed_price' => null,
        ]);

        $base = $this->calculator->calculateBaseAmount(
            ProjectRewardSetting::MODE_PERCENTAGE_OF_SALE,
            null,
            10,
            $reservation,
        );

        $this->assertSame(800000.0, (float) $base['calculation_base_amount']);
        $this->assertSame(80000.0, (float) $base['base_amount']);
    }

    public function test_distribution_pool_amount_excludes_vat(): void
    {
        $vat = $this->calculator->calculateVat(100000, true, 15);

        $this->assertSame(15000.0, (float) $vat['vat_amount']);
        $this->assertSame(115000.0, (float) $vat['total_amount']);
        $this->assertSame(100000.0, (float) $vat['distribution_pool_amount']);
    }
}
