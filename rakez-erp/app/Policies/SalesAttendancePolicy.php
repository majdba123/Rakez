<?php

namespace App\Policies;

use App\Models\SalesAttendanceSchedule;
use App\Models\User;

class SalesAttendancePolicy
{
    /**
     * Determine if user can view any attendance schedules.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.attendance.view') || $user->hasRole('admin');
    }

    /**
     * Determine if user can view a specific attendance schedule.
     */
    public function view(User $user, SalesAttendanceSchedule $schedule): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can view their own schedules
        if ($user->hasPermissionTo('sales.attendance.view')) {
            return $schedule->user_id === $user->id;
        }

        // Leader can view team member schedules
        if ($user->hasPermissionTo('sales.attendance.manage')) {
            $scheduledUser = User::find($schedule->user_id);
            return $scheduledUser && $scheduledUser->team === $user->team;
        }

        return false;
    }

    /**
     * Determine if user can view team attendance (leader only).
     */
    public function viewTeam(User $user): bool
    {
        return $user->hasPermissionTo('sales.attendance.manage') && 
               $user->type === 'sales' && 
               $user->is_manager;
    }

    /**
     * Determine if user can create attendance schedules (leader only).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.attendance.manage') && 
               $user->type === 'sales' && 
               $user->is_manager;
    }

    /**
     * Determine if user can update an attendance schedule (leader only).
     */
    public function update(User $user, SalesAttendanceSchedule $schedule): bool
    {
        // Admin can update any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader who created the schedule can update it
        if ($user->hasPermissionTo('sales.attendance.manage')) {
            return $schedule->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can delete an attendance schedule (leader only).
     */
    public function delete(User $user, SalesAttendanceSchedule $schedule): bool
    {
        // Admin can delete any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader who created the schedule can delete it
        return $user->hasPermissionTo('sales.attendance.manage') && 
               $schedule->created_by === $user->id;
    }
}
