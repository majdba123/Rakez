<?php

namespace App\Policies;

use App\Models\MarketingTask;
use App\Models\User;

class MarketingTaskPolicy
{
    /**
     * Determine if user can view any marketing tasks.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('sales.tasks.manage') || $user->hasRole('admin');
    }

    /**
     * Determine if user can view a specific marketing task.
     */
    public function view(User $user, MarketingTask $task): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader who created the task can view it
        if ($user->hasPermissionTo('sales.tasks.manage')) {
            return $task->created_by === $user->id;
        }

        // Marketer assigned to the task can view it
        return $task->marketer_id === $user->id;
    }

    /**
     * Determine if user can create marketing tasks (leader only).
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sales.tasks.manage') && 
               $user->type === 'sales' && 
               $user->is_manager;
    }

    /**
     * Determine if user can update a marketing task.
     */
    public function update(User $user, MarketingTask $task): bool
    {
        // Admin can update any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader who created the task can update it
        if ($user->hasPermissionTo('sales.tasks.manage')) {
            return $task->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can delete a marketing task (leader only).
     */
    public function delete(User $user, MarketingTask $task): bool
    {
        // Admin can delete any
        if ($user->hasRole('admin')) {
            return true;
        }

        // Leader who created the task can delete it
        return $user->hasPermissionTo('sales.tasks.manage') && 
               $task->created_by === $user->id;
    }
}
