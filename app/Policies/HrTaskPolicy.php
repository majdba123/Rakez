<?php

namespace App\Policies;

use App\Models\HrTask;
use App\Models\User;

class HrTaskPolicy
{
    /**
     * Determine whether the user can view any HR tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the HR task (assignee or creator).
     */
    public function view(User $user, HrTask $hrTask): bool
    {
        return (int) $hrTask->assigned_to === (int) $user->id
            || (int) $hrTask->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can create HR tasks (any authenticated user).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the HR task (assignee only; status/reason only).
     */
    public function update(User $user, HrTask $hrTask): bool
    {
        return (int) $hrTask->assigned_to === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the HR task.
     */
    public function delete(User $user, HrTask $hrTask): bool
    {
        return (int) $hrTask->created_by === (int) $user->id;
    }
}
