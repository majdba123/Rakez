<?php

namespace App\Policies;

use App\Models\ContractUnit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContractUnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('units.view') || $user->can('contracts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ContractUnit $contractUnit): bool
    {
        // Delegate to Contract policy
        $contract = $contractUnit->secondPartyData->contract ?? null;
        
        if (!$contract) {
            // Orphaned unit? Only allow if has general view permission
            return $user->can('units.view');
        }

        return $user->can('view', $contract);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('units.edit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContractUnit $contractUnit): bool
    {
        if (!$user->can('units.edit')) {
            return false;
        }

        $contract = $contractUnit->secondPartyData->contract ?? null;
        
        if (!$contract) {
            return false;
        }

        return $user->can('update', $contract);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContractUnit $contractUnit): bool
    {
        if (!$user->can('units.edit')) {
            return false;
        }

        $contract = $contractUnit->secondPartyData->contract ?? null;
        
        if (!$contract) {
            return false;
        }

        return $user->can('update', $contract);
    }
}
