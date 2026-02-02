<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

class CommissionPolicy
{
    /**
     * Determine if the user can view any commissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sales_manager', 'accountant']);
    }

    /**
     * Determine if the user can view the commission.
     */
    public function view(User $user, Commission $commission): bool
    {
        // Admin, sales manager, and accountant can view all
        if ($user->hasAnyRole(['admin', 'sales_manager', 'accountant'])) {
            return true;
        }

        // Users can view commissions where they have a distribution
        return $commission->distributions()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine if the user can create commissions.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sales_manager']);
    }

    /**
     * Determine if the user can update the commission.
     */
    public function update(User $user, Commission $commission): bool
    {
        // Only pending commissions can be updated
        if (!$commission->isPending()) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'sales_manager']);
    }

    /**
     * Determine if the user can delete the commission.
     */
    public function delete(User $user, Commission $commission): bool
    {
        // Only pending commissions can be deleted
        if (!$commission->isPending()) {
            return false;
        }

        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can approve the commission.
     */
    public function approve(User $user, Commission $commission): bool
    {
        return $user->hasAnyRole(['admin', 'sales_manager']);
    }

    /**
     * Determine if the user can mark commission as paid.
     */
    public function markAsPaid(User $user, Commission $commission): bool
    {
        return $user->hasAnyRole(['admin', 'accountant']);
    }
}
