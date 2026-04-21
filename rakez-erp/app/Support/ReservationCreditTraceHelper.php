<?php

namespace App\Support;

use App\Models\ClaimFile;
use App\Models\SalesReservation;

class ReservationCreditTraceHelper
{
    /**
     * Trace flags for sold reservations: claim file coverage and placeholders for future use.
     * Uses eager-loaded claimFile / combinedClaimFiles when available.
     *
     * @return array{
     *     has_claim_file: int,
     *     claim_file_completed: int,
     *     reserved_1: int,
     *     reserved_2: int
     * }
     */
    public static function traceForSold(SalesReservation $reservation): array
    {
        $claimFile = $reservation->claimFile ?? $reservation->combinedClaimFiles->first();

        $hasClaim = $claimFile !== null ? 1 : 0;
        $completed = 0;
        if ($claimFile !== null) {
            $completed = ($claimFile->status ?? null) === ClaimFile::STATUS_COMPLETED ? 1 : 0;
        }

        return [
            'has_claim_file' => $hasClaim,
            'claim_file_completed' => $completed,
            'reserved_1' => 0,
            'reserved_2' => 0,
        ];
    }
}
