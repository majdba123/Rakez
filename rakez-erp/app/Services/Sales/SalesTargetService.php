<?php

namespace App\Services\Sales;

use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalesTargetService
{
    /**
     * Get targets for the current user. For sales leaders: all targets assigned to their team.
     * For other sales users: only their own targets.
     */
    public function getMyTargets(User $user, array $filters): LengthAwarePaginator
    {
        $query = SalesTarget::query()
            ->with(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer']);

        if ($user->hasRole('sales_leader') && $user->team_id) {
            $teamMemberIds = User::where('team_id', $user->team_id)->pluck('id');
            $query->whereIn('marketer_id', $teamMemberIds);
        } else {
            $query->where('marketer_id', $user->id);
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        $perPage = $filters['per_page'] ?? 15;
        return $query->orderBy('start_date', 'desc')->paginate($perPage);
    }

    /**
     * Get targets for a project (contract) for the user's team. Allowed only if the user
     * has at least one target for this contract, or their team has targets for this contract.
     */
    public function getTargetsByProject(int $contractId, User $user): Collection
    {
        if (!$this->userCanViewTargetsByProject($user, $contractId)) {
            throw new \Exception('You do not have access to targets for this project');
        }

        $query = SalesTarget::where('contract_id', $contractId);

        if ($user->team_id) {
            $teamMemberIds = User::where('team_id', $user->team_id)->pluck('id');
            $query->whereIn('marketer_id', $teamMemberIds);
        } else {
            $query->where('marketer_id', $user->id);
        }

        return $query
            ->with(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Check if user can view targets for a given contract (has a target for it or team has targets).
     */
    public function userCanViewTargetsByProject(User $user, int $contractId): bool
    {
        $hasOwnTarget = SalesTarget::where('contract_id', $contractId)
            ->where('marketer_id', $user->id)
            ->exists();
        if ($hasOwnTarget) {
            return true;
        }
        if ($user->team_id) {
            $teamMemberIds = User::where('team_id', $user->team_id)->pluck('id');
            return SalesTarget::where('contract_id', $contractId)
                ->whereIn('marketer_id', $teamMemberIds)
                ->exists();
        }
        return false;
    }

    /**
     * Create a target (leader only).
     */
    public function createTarget(array $data, User $leader): SalesTarget
    {
        // Validate leader has access to this project
        if (!$this->leaderHasAccessToProject($leader, $data['contract_id'])) {
            throw new \Exception('Leader is not assigned to this project');
        }

        // Validate marketer is in same team (use team_id for consistency)
        $marketer = User::findOrFail($data['marketer_id']);
        if ($marketer->team_id !== $leader->team_id || $marketer->team_id === null) {
            throw new \Exception('Marketer must be in the same team as leader');
        }

        // Normalize unit ids: support contract_unit_ids (array) or contract_unit_id (single)
        $unitIds = $data['contract_unit_ids'] ?? null;
        if ($unitIds === null && !empty($data['contract_unit_id'])) {
            $unitIds = [(int) $data['contract_unit_id']];
        }
        $unitIds = is_array($unitIds) ? array_filter(array_map('intval', $unitIds)) : [];

        // Validate each unit belongs to the selected project
        foreach ($unitIds as $unitId) {
            $unit = ContractUnit::with('secondPartyData')->find($unitId);
            if (!$unit || !$unit->secondPartyData || (int) $unit->secondPartyData->contract_id !== (int) $data['contract_id']) {
                throw new \Exception('Unit does not belong to the selected project');
            }
        }

        $target = SalesTarget::create([
            'leader_id' => $leader->id,
            'marketer_id' => $data['marketer_id'],
            'contract_id' => $data['contract_id'],
            'contract_unit_id' => $unitIds[0] ?? null,
            'target_type' => $data['target_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'leader_notes' => $data['leader_notes'] ?? null,
        ]);

        if (!empty($unitIds)) {
            $target->contractUnits()->sync($unitIds);
        }

        return $target->fresh(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer']);
    }

    /**
     * Update target status (marketer only).
     */
    public function updateTargetStatus(int $targetId, string $status, User $marketer): SalesTarget
    {
        $target = SalesTarget::findOrFail($targetId);

        // Validate marketer owns this target
        if ($target->marketer_id !== $marketer->id) {
            throw new \Exception('Unauthorized to update this target');
        }

        $target->update(['status' => $status]);

        return $target->fresh();
    }

    /**
     * Update target (wrapper for controller).
     */
    public function updateTarget(int $targetId, array $data, User $user): SalesTarget
    {
        return $this->updateTargetStatus($targetId, $data['status'], $user);
    }

    /**
     * Check if leader has access to a project.
     */
    protected function leaderHasAccessToProject(User $leader, int $contractId): bool
    {
        return \App\Models\SalesProjectAssignment::where('leader_id', $leader->id)
            ->where('contract_id', $contractId)
            ->exists();
    }
}
