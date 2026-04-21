<?php

namespace App\Support;

use App\Models\ClaimFile;
use App\Models\SalesReservation;

class ReservationCreditTraceHelper
{
    /**
     * Trace flags for sold reservations: claim file, commission record (accounting), future slot.
     * Uses eager-loaded claimFile / combinedClaimFiles / commission when available.
     *
     * @return array{
     *     has_claim_file: int,
     *     claim_file_completed: int,
     *     has_commission: int,
     *     distribution_approved: int
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

        $hasCommission = $reservation->commission !== null ? 1 : 0;

        $distributionApproved = 0;
        $commission = $reservation->commission;
        if ($commission !== null) {
            if ($commission->relationLoaded('distributions')) {
                $distributionApproved = $commission->distributions->contains(
                    static fn ($d) => in_array($d->status ?? '', ['approved', 'paid'], true)
                ) ? 1 : 0;
            } else {
                $distributionApproved = $commission->distributions()
                    ->whereIn('status', ['approved', 'paid'])
                    ->exists()
                    ? 1
                    : 0;
            }
        }

        return [
            'has_claim_file' => $hasClaim,
            'claim_file_completed' => $completed,
            'has_commission' => $hasCommission,
            'distribution_approved' => $distributionApproved,
        ];
    }
}
