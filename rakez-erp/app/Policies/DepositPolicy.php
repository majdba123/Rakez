<?php

namespace App\Policies;

use App\Models\Deposit;
use App\Models\User;

class DepositPolicy
{
    /**
     * Determine if the user can view any deposits.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sales_manager', 'accountant', 'sales']);
    }

    /**
     * Determine if the user can view the deposit.
     */
    public function view(User $user, Deposit $deposit): bool
    {
        // Admin, sales manager, and accountant can view all
        if ($user->hasAnyRole(['admin', 'sales_manager', 'accountant'])) {
            return true;
        }

        // Sales staff can view deposits for their reservations
        if ($user->hasRole('sales')) {
            return $deposit->salesReservation && 
                   $deposit->salesReservation->marketing_employee_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create deposits.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'sales_manager', 'sales', 'accountant']);
    }

    /**
     * Determine if the user can update the deposit.
     */
    public function update(User $user, Deposit $deposit): bool
    {
        // Only pending deposits can be updated
        if (!$deposit->isPending()) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'sales_manager', 'accountant']);
    }

    /**
     * Determine if the user can delete the deposit.
     */
    public function delete(User $user, Deposit $deposit): bool
    {
        // Only pending deposits can be deleted
        if (!$deposit->isPending()) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'sales_manager']);
    }

    /**
     * Determine if the user can confirm deposit receipt.
     */
    public function confirmReceipt(User $user, Deposit $deposit): bool
    {
        return $user->hasAnyRole(['admin', 'accountant', 'sales_manager']);
    }

    /**
     * Determine if the user can refund the deposit.
     */
    public function refund(User $user, Deposit $deposit): bool
    {
        return $user->hasAnyRole(['admin', 'accountant', 'sales_manager']);
    }
}
