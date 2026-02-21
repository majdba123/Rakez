<?php

namespace App\Policies;

use App\Models\MarketingSetting;
use App\Models\User;

class MarketingSettingPolicy
{
    /**
     * Determine whether the user can view any marketing settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('marketing.dashboard.view');
    }

    /**
     * Determine whether the user can view the marketing setting.
     */
    public function view(User $user, MarketingSetting $marketingSetting): bool
    {
        return $user->can('marketing.dashboard.view');
    }

    /**
     * Determine whether the user can create marketing settings.
     */
    public function create(User $user): bool
    {
        return $user->can('marketing.budgets.manage');
    }

    /**
     * Determine whether the user can update the marketing setting.
     */
    public function update(User $user, MarketingSetting $marketingSetting): bool
    {
        return $user->can('marketing.budgets.manage');
    }

    /**
     * Determine whether the user can delete the marketing setting.
     */
    public function delete(User $user, MarketingSetting $marketingSetting): bool
    {
        return $user->can('marketing.budgets.manage');
    }
}
