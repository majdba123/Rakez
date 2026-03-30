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
}
