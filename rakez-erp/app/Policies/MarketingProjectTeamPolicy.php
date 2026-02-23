<?php

namespace App\Policies;

use App\Models\MarketingProjectTeam;
use App\Models\User;

class MarketingProjectTeamPolicy
{
    /**
     * Determine whether the user can view any marketing project teams.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('marketing.projects.view');
    }

    /**
     * Determine whether the user can view the marketing project team.
     */
    public function view(User $user, MarketingProjectTeam $marketingProjectTeam): bool
    {
        return $user->can('marketing.projects.view');
    }

    /**
     * Determine whether the user can create marketing project teams.
     */
    public function create(User $user): bool
    {
        return $user->can('marketing.projects.view');
    }

    /**
     * Determine whether the user can update the marketing project team.
     */
    public function update(User $user, MarketingProjectTeam $marketingProjectTeam): bool
    {
        return $user->can('marketing.projects.view');
    }

    /**
     * Determine whether the user can delete the marketing project team.
     */
    public function delete(User $user, MarketingProjectTeam $marketingProjectTeam): bool
    {
        return $user->can('marketing.projects.view');
    }
}
