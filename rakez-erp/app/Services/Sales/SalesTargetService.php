<?php

namespace App\Services\Sales;

use App\Models\ContractUnit;
use App\Models\SalesProjectAssignment;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SalesTargetService
{
    public const MY_CONTENT_ASSIGNMENTS = 'assignments';
    public const MY_CONTENT_TARGETS = 'targets';

    /**
     * Get content for "my" targets page. For sales leaders: projects assigned to their team (assignments).
     * For other sales users: only their own targets.
     *
     * @return array{type: string, paginator: LengthAwarePaginator}
     */
    public function getMyTargets(User $user, array $filters): array
    {
        if ($user->hasRole('sales_leader')) {
            return [
                'type' => self::MY_CONTENT_ASSIGNMENTS,
                'paginator' => $this->getAssignedProjectsForLeader($user, $filters),
            ];
        }

        $query = SalesTarget::query()
            ->with(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer'])
            ->where('marketer_id', $user->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        return [
            'type' => self::MY_CONTENT_TARGETS,
            'paginator' => $query->orderBy('start_date', 'desc')->paginate($perPage),
        ];
    }

    /**
     * Get projects assigned to the leader (for sales leader "my" page).
     */
    public function getAssignedProjectsForLeader(User $leader, array $filters): LengthAwarePaginator
    {
        $accessibleLeaderIds = $this->getAccessibleLeaderIdsForLeader($leader);

        $query = SalesProjectAssignment::query()
            ->whereIn('leader_id', $accessibleLeaderIds)
            ->with(['contract', 'assignedBy']);

        if (!empty($filters['from'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $filters['from']);
            });
        }
        if (!empty($filters['to'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $filters['to']);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
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
     * Check if user can view targets for a given contract (has a target for it, team has targets, or leader has assignment).
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
            if (SalesTarget::where('contract_id', $contractId)->whereIn('marketer_id', $teamMemberIds)->exists()) {
                return true;
            }
        }
        // Leader can view by-project for contracts assigned to them (even if no targets yet).
        if ($user->hasRole('sales_leader')) {
            $accessibleLeaderIds = $this->getAccessibleLeaderIdsForLeader($user);
            return SalesProjectAssignment::whereIn('leader_id', $accessibleLeaderIds)
                ->where('contract_id', $contractId)
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
        $leaderTeamId = $leader->team_id;
        $marketerTeamId = $marketer->team_id;

        // Prefer team_id when both exist, otherwise fallback to `team` name (used by some tests/factories).
        if ($leaderTeamId !== null && $marketerTeamId !== null) {
            if ($marketerTeamId !== $leaderTeamId) {
                throw new \Exception('Marketer must be in the same team as leader');
            }
        } else {
            if (empty($leader->team) || empty($marketer->team) || $marketer->team !== $leader->team) {
                throw new \Exception('Marketer must be in the same team as leader');
            }
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
        $accessibleLeaderIds = $this->getAccessibleLeaderIdsForLeader($leader);
        return \App\Models\SalesProjectAssignment::whereIn('leader_id', $accessibleLeaderIds)
            ->where('contract_id', $contractId)
            ->exists();
    }

    /**
     * A sales leader should be able to see/manage projects assigned to their team leaders.
     * If the leader has a team_id: include all users in that team where is_manager=true.
     * Otherwise: return only the leader id.
     */
    protected function getAccessibleLeaderIdsForLeader(User $leader): array
    {
        if (empty($leader->team_id)) {
            return [$leader->id];
        }

        $leaderIds = \App\Models\User::where('team_id', $leader->team_id)
            ->where('is_manager', true)
            ->pluck('id')
            ->all();

        // Always include the requesting leader (safety).
        if (!in_array($leader->id, $leaderIds, true)) {
            $leaderIds[] = $leader->id;
        }

        return array_values(array_unique(array_map('intval', $leaderIds)));
    }
}
