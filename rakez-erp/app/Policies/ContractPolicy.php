<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContractPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('contracts.view') || $user->can('contracts.view_all');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Contract $contract): bool
    {
        if ($user->can('contracts.view_all')) {
            return true;
        }

        // Owner can view
        if ($contract->user_id === $user->id) {
            return true;
        }

        // Manager can view team's contracts
        if ($user->isManager() && $user->team && $contract->user && $contract->user->team === $user->team) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('contracts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contract $contract): bool
    {
        // Owner can update
        if ($contract->user_id === $user->id) {
            return true;
        }

        // Manager can update team's contracts
        if ($user->isManager() && $user->team && $contract->user && $contract->user->team === $user->team) {
            return true;
        }

        // Special permission to approve/update status
        if ($user->can('contracts.approve')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contract $contract): bool
    {
        // Owner can delete
        if ($contract->user_id === $user->id) {
            return true;
        }

        if ($user->can('contracts.delete')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contract $contract): bool
    {
        return $user->can('contracts.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contract $contract): bool
    {
        return $user->can('contracts.delete');
    }
}
