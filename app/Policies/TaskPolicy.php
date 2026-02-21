<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task (assignee or creator).
     */
    public function view(User $user, Task $task): bool
    {
        return (int) $task->assigned_to === (int) $user->id
            || (int) $task->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can create tasks (any authenticated user).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the task (assignee only; status/reason only).
     */
    public function update(User $user, Task $task): bool
    {
        return (int) $task->assigned_to === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return (int) $task->created_by === (int) $user->id;
    }
}
