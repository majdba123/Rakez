<?php

namespace App\Policies;

use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Sales\SalesTargetService;

class SalesTargetPolicy
{
    /**
     * Determine if user can view team targets for a project (by contract id). Allowed if user has at least one target for this contract, or their team has targets for it.
     */
    public function viewTargetsByProject(User $user, int $contractId): bool
    {
        return app(SalesTargetService::class)->userCanViewTargetsByProject($user, $contractId);
    }

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
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($target->marketer_id === $user->id) {
            return true;
        }

        if ($user->isSalesLeader() && $user->team_id) {
            return $target->contract()
                ->where('status', 'completed')
                ->whereHas('teams', fn ($teams) => $teams->where('teams.id', $user->team_id))
                ->exists()
                && $target->marketer()
                    ->where('team_id', $user->team_id)
                    ->where('type', 'sales')
                    ->where('is_manager', false)
                    ->exists();
        }

        return false;
    }

    /**
     * Determine if user can create targets (leader only).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.team.manage')
            && $user->type === 'sales'
            && $user->is_manager;
    }

    /**
     * Determine if user can update a target.
     */
    public function update(User $user, SalesTarget $target): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasPermissionTo('sales.targets.update')
            && $target->marketer_id === $user->id;
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
