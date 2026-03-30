<?php

namespace App\Enums;

/**
 * Computed sales listing readiness on Contract (not a DB column; set in SalesProjectService).
 */
enum SalesProjectListingStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
}
