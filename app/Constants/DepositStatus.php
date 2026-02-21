<?php

namespace App\Constants;

final class DepositStatus
{
    public const PENDING = 'pending';

    public const RECEIVED = 'received';

    public const CONFIRMED = 'confirmed';

    public const REFUNDED = 'refunded';

    /**
     * Statuses considered as "received or confirmed" for accounting/sales.
     *
     * @return array<int, string>
     */
    public static function receivedOrConfirmed(): array
    {
        return [
            self::RECEIVED,
            self::CONFIRMED,
        ];
    }
}
