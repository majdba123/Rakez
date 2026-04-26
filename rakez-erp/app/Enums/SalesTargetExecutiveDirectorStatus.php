<?php

namespace App\Enums;

/**
 * Status of an executive-director line under a sales target.
 */
enum SalesTargetExecutiveDirectorStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
