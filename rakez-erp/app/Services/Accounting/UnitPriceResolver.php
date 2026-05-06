<?php

namespace App\Services\Accounting;

use App\Exceptions\Accounting\UnitPriceUnresolvedException;
use App\Models\SalesReservation;

class UnitPriceResolver
{
    /**
     * @throws UnitPriceUnresolvedException
     */
    public function resolve(SalesReservation $reservation): float
    {
        $reservation->loadMissing(['commission', 'contractUnit']);

        $commission = $reservation->commission;
        if ($commission !== null && $commission->final_selling_price !== null) {
            $fromCommission = round((float) $commission->final_selling_price, 2);
            if ($fromCommission > 0) {
                return $fromCommission;
            }
        }

        if ($reservation->proposed_price !== null) {
            $proposed = round((float) $reservation->proposed_price, 2);
            if ($proposed > 0) {
                return $proposed;
            }
        }

        $unit = $reservation->contractUnit;
        if ($unit !== null && $unit->price !== null) {
            $unitPrice = round((float) $unit->price, 2);
            if ($unitPrice > 0) {
                return $unitPrice;
            }
        }

        throw new UnitPriceUnresolvedException('Could not resolve a positive unit price for this reservation.');
    }
}
