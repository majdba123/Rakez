<?php

namespace App\Policies;

use App\Models\MarketingTask;
use App\Models\User;

class MarketingTaskPolicy
{
    /**
     * Determine whether the user can view any marketing tasks.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('marketing.tasks.view');
    }

    /**
     * Determine whether the user can view the marketing task.
     */
    public function view(User $user, MarketingTask $marketingTask): bool
    {
        return $user->can('marketing.tasks.view');
    }

    /**
     * Determine whether the user can create marketing tasks.
     */
    public function create(User $user): bool
    {
        return $user->can('marketing.tasks.view');
    }

    /**
     * Determine whether the user can update the marketing task.
     */
    public function update(User $user, MarketingTask $marketingTask): bool
    {
        return $user->can('marketing.tasks.confirm');
    }

    /**
     * Determine whether the user can delete the marketing task.
     */
    public function delete(User $user, MarketingTask $marketingTask): bool
    {
        return $user->can('marketing.tasks.confirm');
    }
}
