<?php

namespace App\Enums;

/**
 * Contract lifecycle on `contracts.status` (see migrations extending contracts).
 */
enum ContractWorkflowStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [
                $status->value => ucfirst($status->value),
            ])
            ->all();
    }
}
