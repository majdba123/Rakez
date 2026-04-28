<?php

namespace App\Enums;

enum ExecutiveDirectorLineStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
