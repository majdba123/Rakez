<?php

namespace App\Policies;

use App\Models\SalesTarget;
use App\Models\User;

class SalesTargetPolicy
{
    /**
     * Determine if user can view any targets.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.targets.view') || $user->hasRole('admin');
    }

    /**
     * Determine if user can view a specific target.
     */
    public function view(User $user, SalesTarget $target): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Marketer can view their own targets
        if ($user->hasPermissionTo('sales.targets.view')) {
            return $target->marketer_id === $user->id;
        }

        // Leader can view targets they assigned
        if ($user->hasPermissionTo('sales.team.manage')) {
            return $target->leader_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can create targets (leader only).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.team.manage') && 
               $user->type === 'sales' && 
               $user->is_manager;
    }

    /**
     * Determine if user can update a target.
     */
    public function update(User $user, SalesTarget $target): bool
    {
        // Admin can update any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Marketer can update status of their own targets
        if ($user->hasPermissionTo('sales.targets.update')) {
            return $target->marketer_id === $user->id;
        }

        // Leader can update targets they assigned
        if ($user->hasPermissionTo('sales.team.manage')) {
            return $target->leader_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can delete a target (leader only).
     */
    public function delete(User $user, SalesTarget $target): bool
    {
        // Admin can delete any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader can delete targets they assigned
        return $user->hasPermissionTo('sales.team.manage') && 
               $target->leader_id === $user->id;
    }
}
