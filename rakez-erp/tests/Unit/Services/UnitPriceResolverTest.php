<?php

namespace Tests\Unit\Services;

use App\Exceptions\Accounting\UnitPriceUnresolvedException;
use App\Models\Commission;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Services\Accounting\UnitPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitPriceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_existing_commission_final_selling_price(): void
    {
        $reservation = SalesReservation::factory()->create([
            'proposed_price' => 75000,
        ]);

        $unit = ContractUnit::find($reservation->contract_unit_id);
        $unit?->update(['price' => 60000]);

        Commission::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'final_selling_price' => 99999,
        ]);

        $price = app(UnitPriceResolver::class)->resolve($reservation->fresh());

        $this->assertSame(99999.0, $price);
    }

    public function test_falls_back_to_proposed_price_when_commission_missing_or_invalid(): void
    {
        $reservation = SalesReservation::factory()->create([
            'proposed_price' => 81234.56,
        ]);

        $resolver = app(UnitPriceResolver::class);
        $this->assertSame(81234.56, $resolver->resolve($reservation));

        Commission::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'final_selling_price' => 0,
        ]);

        $this->assertSame(81234.56, $resolver->resolve($reservation->fresh()));
    }

    public function test_falls_back_to_contract_unit_price(): void
    {
        $reservation = SalesReservation::factory()->create([
            'proposed_price' => null,
        ]);

        $unit = ContractUnit::findOrFail($reservation->contract_unit_id);
        $unit->price = 125000;
        $unit->save();

        $price = app(UnitPriceResolver::class)->resolve($reservation->fresh());

        $this->assertSame(125000.0, $price);
    }

    public function test_fails_when_no_positive_price_can_be_found(): void
    {
        $this->expectException(UnitPriceUnresolvedException::class);

        $reservation = SalesReservation::factory()->create([
            'proposed_price' => null,
        ]);

        $unit = ContractUnit::findOrFail($reservation->contract_unit_id);
        $unit->price = 0;
        $unit->save();

        app(UnitPriceResolver::class)->resolve($reservation->fresh());
    }
}
