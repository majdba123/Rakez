<?php

namespace App\Services\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Support\Governance\GovernanceStatusCatalog;
use Illuminate\Database\Eloquent\Builder;
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
        if ($user->isSalesLeader()) {
            return [
                'type' => self::MY_CONTENT_ASSIGNMENTS,
                'paginator' => $this->getGoalProjectsForLeader($user, $filters),
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
     * Get PM-linked team projects for the leader "my targets" page.
     */
    public function getGoalProjectsForLeader(User $leader, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        if (!$leader->team_id) {
            return $this->emptyPaginator($perPage);
        }

        $query = Contract::query()
            ->select('contracts.*', 'contract_team.created_at as team_attached_at')
            ->join('contract_team', function ($join) use ($leader) {
                $join->on('contract_team.contract_id', '=', 'contracts.id')
                    ->where('contract_team.team_id', '=', $leader->team_id);
            })
            ->where('contracts.status', 'completed')
            ->with([
                'teams' => fn ($q) => $q->where('teams.id', $leader->team_id),
            ]);

        if (!empty($filters['from'])) {
            $query->whereDate('contract_team.created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('contract_team.created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('contract_team.created_at')->paginate($perPage);
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

        $query = SalesTarget::where('contract_id', $contractId)
            ->with(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer']);

        if ($user->hasRole('admin')) {
            return $query->orderBy('start_date', 'desc')->get();
        }

        if ($user->isSalesLeader()) {
            $query->whereHas('marketer', function (Builder $marketers) use ($user) {
                $marketers
                    ->where('team_id', $user->team_id)
                    ->where('type', 'sales')
                    ->where('is_manager', false);
            });
        } else {
            $query->where('marketer_id', $user->id);
        }

        return $query->orderBy('start_date', 'desc')->get();
    }

    /**
     * Check if user can view targets for a given contract.
     * Leaders: PM-linked team projects only. Marketers: own targets only.
     */
    public function userCanViewTargetsByProject(User $user, int $contractId): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->isSalesLeader()) {
            return $this->leaderTeamHasProject($user, $contractId);
        }

        return SalesTarget::where('contract_id', $contractId)
            ->where('marketer_id', $user->id)
            ->exists();
    }

    /**
     * Create a target (leader only).
     */
    public function createTarget(array $data, User $leader): SalesTarget
    {
        if (!$leader->team_id) {
            throw new \Exception('Leader must belong to a team');
        }

        if (!$this->leaderTeamHasProject($leader, (int) $data['contract_id'])) {
            throw new \Exception('Project is not assigned to the leader team by project management');
        }

        $marketer = User::findOrFail($data['marketer_id']);

        if (($marketer->type ?? null) !== 'sales' || (bool) $marketer->is_manager) {
            throw new \Exception('Target assignee must be a sales marketer');
        }

        if ((int) $marketer->team_id !== (int) $leader->team_id) {
            throw new \Exception('Marketer must be in the same team as leader');
        }

        // Normalize unit ids: support contract_unit_ids (array) or contract_unit_id (single)
        $unitIds = $data['contract_unit_ids'] ?? null;
        if ($unitIds === null && !empty($data['contract_unit_id'])) {
            $unitIds = [(int) $data['contract_unit_id']];
        }
        $unitIds = is_array($unitIds) ? array_values(array_unique(array_filter(array_map('intval', $unitIds)))) : [];

        // Validate each unit belongs to the selected project
        foreach ($unitIds as $unitId) {
            $unit = ContractUnit::with('contract')->find($unitId);
            if (!$unit || (int) $unit->contract_id !== (int) $data['contract_id']) {
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
     * Check if a PM-linked team project is available to the leader.
     */
    protected function leaderTeamHasProject(User $leader, int $contractId): bool
    {
        if (!$leader->team_id) {
            return false;
        }

        return Contract::query()
            ->whereKey($contractId)
            ->where('status', 'completed')
            ->whereHas('teams', function (Builder $teams) use ($leader) {
                $teams->where('teams.id', $leader->team_id);
            })
            ->exists();
    }

    /**
     * Build an empty paginator with stable API metadata.
     */
    protected function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Governance panel: update any target status when actor has permission `sales.targets.update`.
     * Bypasses marketer-ownership checks used by {@see updateTargetStatus()} for operational sales users.
     */
    public function governanceUpdateTargetStatus(int $targetId, string $status, User $actor): SalesTarget
    {
        abort_unless($actor->can('sales.targets.update'), 403);

        if (! in_array($status, GovernanceStatusCatalog::salesTargetStatuses(), true)) {
            throw new \InvalidArgumentException('Invalid target status');
        }

        $target = SalesTarget::findOrFail($targetId);

        $beforeStatus = $target->status;

        $target->update(['status' => $status]);

        $fresh = $target->fresh(['contract', 'contractUnit', 'contractUnits', 'leader', 'marketer']);

        app(GovernanceAuditLogger::class)->log('governance.sales.target.status_updated', $fresh, [
            'before' => ['status' => $beforeStatus],
            'after' => ['status' => $fresh->status],
        ], $actor);

        return $fresh;
    }
}
