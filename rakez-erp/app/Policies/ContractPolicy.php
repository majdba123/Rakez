<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\SalesProjectAssignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ContractPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('contracts.view') || $user->can('contracts.view_all');
    }

    /**
     * Determine whether the user can view the model.
     * مدير مبيعات/موظف مبيعات: فقط عقود المشاريع المسندة لفريق المستخدم (توافق مع sales/projects).
     */
    public function view(User $user, Contract $contract): bool
    {
        if ($user->can('contracts.view_all')) {
            return true;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        // Sales / sales_leader: team-assigned contracts, or any contract if they have second_party.view (for second-party-data endpoint)
        if ($user->hasAnyRole(['sales', 'sales_leader'])) {
            if (SalesProjectAssignment::where('leader_id', $user->id)
                ->where('contract_id', $contract->id)
                ->active()
                ->exists()) {
                return true;
            }
            if ($user->team_id) {
                $teamLeaderIds = User::where('team_id', $user->team_id)
                    ->where('is_manager', true)
                    ->pluck('id');
                if (SalesProjectAssignment::where('contract_id', $contract->id)
                    ->whereIn('leader_id', $teamLeaderIds)
                    ->active()
                    ->exists()) {
                    return true;
                }
            }
            if ($user->can('second_party.view')) {
                return true;
            }
            return false;
        }

        if ($user->can('sales.projects.view') || $user->can('marketing.projects.view')) {
            return true;
        }

        if ($contract->user_id === $user->id) {
            return true;
        }

        if ($user->isManager() && $user->team_id && $contract->user && (int) $contract->user->team_id == (int) $user->team_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('contracts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contract $contract): bool
    {
        // Owner can update
        if ($contract->user_id === $user->id) {
            return true;
        }

        // Manager can update team's contracts (compare team_id — relation === is always false across instances)
        if ($user->isManager() && $user->team_id && $contract->user && (int) $contract->user->team_id == (int) $user->team_id) {
            return true;
        }

        // Special permission to approve/update status
        if ($user->can('contracts.approve')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contract $contract): bool
    {
        // Owner can delete
        if ($contract->user_id === $user->id) {
            return true;
        }

        if ($user->can('contracts.delete')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contract $contract): bool
    {
        return $user->can('contracts.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contract $contract): bool
    {
        return $user->can('contracts.delete');
    }
}
