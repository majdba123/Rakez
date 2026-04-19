<?php

namespace Tests\Unit\Marketing;

use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Services\Marketing\ContractPricingBasisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractPricingBasisServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prefers_override_over_unit_sums(): void
    {
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 100,
        ]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 400000]);
        $contract->refresh();

        $svc = app(ContractPricingBasisService::class);
        $basis = $svc->resolve($contract, ['total_unit_price_override' => 999]);

        $this->assertSame(ContractPricingBasisService::SOURCE_OVERRIDE, $basis['source']);
        $this->assertSame(999.0, $basis['total_unit_price']);
    }

    #[Test]
    public function it_uses_available_units_sum_for_commission_base_when_no_override(): void
    {
        // Business rule: commission base = available units only, not all units
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 1,
        ]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 300000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 700000]);
        $contract->refresh();

        $svc = app(ContractPricingBasisService::class);
        $basis = $svc->resolve($contract, []);

        $this->assertSame(ContractPricingBasisService::SOURCE_AVAILABLE_UNITS, $basis['source']);
        // Commission base = available units only (300k), not all units (1000k)
        $this->assertSame(300000.0, $basis['commission_base_amount']);
        $this->assertSame(300000.0, $basis['total_unit_price_available_sum']);
        // All-units fields remain in response as informational only
        $this->assertSame(1000000.0, $basis['total_unit_price_all_sum']);
        $this->assertSame(300000.0, $basis['average_unit_price_available']);
        $this->assertSame(500000.0, $basis['average_unit_price_all']);
        // Canonical average_unit_price must now equal available-only average
        $this->assertSame(300000.0, $basis['average_unit_price']);
    }

    #[Test]
    public function it_falls_back_to_avg_stored_when_no_available_units(): void
    {
        // When all units are sold/pending (none available), fall back to stored avg
        $contract = Contract::factory()->create();
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 800000,
        ]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 500000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 600000]);
        $contract->refresh();

        $svc = app(ContractPricingBasisService::class);
        $basis = $svc->resolve($contract, []);

        $this->assertSame(ContractPricingBasisService::SOURCE_AVG_STORED, $basis['source']);
        $this->assertSame(800000.0, $basis['commission_base_amount']);
    }
}
