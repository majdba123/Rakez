<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesDashboardPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the sales dashboard.
     */
    public function viewAny(User $user): bool
    {
        // Only users with sales, sales_leader, or admin roles can access
        return $user->hasAnyRole(['sales', 'sales_leader', 'admin']);
    }
}
