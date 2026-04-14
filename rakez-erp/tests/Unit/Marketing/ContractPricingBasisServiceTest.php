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
    public function it_uses_all_units_sum_not_available_only_when_no_override(): void
    {
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

        $this->assertSame(ContractPricingBasisService::SOURCE_ALL_UNITS, $basis['source']);
        $this->assertSame(1000000.0, $basis['commission_base_amount']);
        $this->assertSame(300000.0, $basis['total_unit_price_available_sum']);
        $this->assertSame(1000000.0, $basis['total_unit_price_all_sum']);
        $this->assertSame(300000.0, $basis['average_unit_price_available']);
        $this->assertSame(500000.0, $basis['average_unit_price_all']);
    }
}
