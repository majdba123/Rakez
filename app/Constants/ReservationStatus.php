<?php

namespace App\Constants;

final class ReservationStatus
{
    public const UNDER_NEGOTIATION = 'under_negotiation';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';

    /**
     * Statuses used for credit booking list (All tab): confirmed, under_negotiation, cancelled.
     *
     * @return array<int, string>
     */
    public static function forCreditBookingList(): array
    {
        return [
            self::CONFIRMED,
            self::UNDER_NEGOTIATION,
            self::CANCELLED,
        ];
    }

    /**
     * Active statuses: under_negotiation, confirmed.
     *
     * @return array<int, string>
     */
    public static function active(): array
    {
        return [
            self::UNDER_NEGOTIATION,
            self::CONFIRMED,
        ];
    }
}
