<?php

namespace App\Enums;

/**
 * Typical `contract_units.status` values used across sales / inventory.
 */
enum ContractUnitWorkflowStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case UnderNegotiation = 'under_negotiation';
}
