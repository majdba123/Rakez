<?php

namespace App\Policies;

use App\Models\SalesReservation;
use App\Models\User;

class SalesReservationPolicy
{
    /**
     * Determine if user can view any reservations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.reservations.view') || $user->hasRole('admin');
    }

    /**
     * Determine if user can view a specific reservation.
     */
    public function view(User $user, SalesReservation $reservation): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales user can view their own reservations
        if ($user->hasPermissionTo('sales.reservations.view')) {
            return $reservation->marketing_employee_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can create reservations.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.reservations.create');
    }

    /**
     * Determine if user can confirm a reservation.
     */
    public function confirm(User $user, SalesReservation $reservation): bool
    {
        // Admin can confirm any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales user can confirm their own reservations
        if ($user->hasPermissionTo('sales.reservations.confirm')) {
            return $reservation->marketing_employee_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can cancel a reservation.
     */
    public function cancel(User $user, SalesReservation $reservation): bool
    {
        // Admin can cancel any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales user can cancel their own reservations
        if ($user->hasPermissionTo('sales.reservations.cancel')) {
            return $reservation->marketing_employee_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can log actions on a reservation.
     */
    public function logAction(User $user, SalesReservation $reservation): bool
    {
        // Admin can log actions on any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales user can log actions on their own reservations
        return $reservation->marketing_employee_id === $user->id;
    }

    /**
     * Determine if user can download a voucher.
     */
    public function downloadVoucher(User $user, SalesReservation $reservation): bool
    {
        // Admin can download any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales user can download their own vouchers
        return $reservation->marketing_employee_id === $user->id;
    }
}
