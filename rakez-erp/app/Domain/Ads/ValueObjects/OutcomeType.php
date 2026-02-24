<?php

namespace App\Domain\Ads\ValueObjects;

enum OutcomeType: string
{
    case LeadQualified = 'LEAD_QUALIFIED';
    case LeadDisqualified = 'LEAD_DISQUALIFIED';
    case DealWon = 'DEAL_WON';
    case DealLost = 'DEAL_LOST';
    case Purchase = 'PURCHASE';
    case Refund = 'REFUND';
    case RetentionD7 = 'RETENTION_D7';
    case RetentionD30 = 'RETENTION_D30';
    case LtvUpdate = 'LTV_UPDATE';

    public function isPositive(): bool
    {
        return in_array($this, [
            self::LeadQualified,
            self::DealWon,
            self::Purchase,
            self::RetentionD7,
            self::RetentionD30,
            self::LtvUpdate,
        ]);
    }
}
