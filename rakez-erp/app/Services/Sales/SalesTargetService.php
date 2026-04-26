<?php

namespace App\Services\Sales;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesTarget;
use App\Models\User;
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
            ->with(['contract.city', 'contract.district', 'contractUnit', 'contractUnits', 'leader', 'marketer'])
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
                'city',
                'district',
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
            ->with(['contract.city', 'contract.district', 'contractUnit', 'contractUnits', 'leader', 'marketer']);

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
     * Targets whose assigning leader (leader_id) is a user with is_executive_director = true.
     */
    public function listTargetsCreatedByExecutiveLeader(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = min(max($perPage, 1), 100);

        $query = SalesTarget::query()
            ->with(['contract.city', 'contract.district', 'contractUnit', 'contractUnits', 'leader', 'marketer'])
            ->whereHas('leader', function (Builder $q) {
                $q->where('is_executive_director', true);
            });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->dateRange($filters['from'] ?? null, $filters['to'] ?? null);
        }
        if (! empty($filters['contract_id'])) {
            $query->where('contract_id', (int) $filters['contract_id']);
        }
        if (! empty($filters['leader_id'])) {
            $query->where('leader_id', (int) $filters['leader_id']);
        }

        return $query->orderBy('start_date', 'desc')->paginate($perPage);
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
     * Create a sales performance target (leader only).
     * Does not assign inventory: {@see SalesTarget::$must_sell_units_count} and {@see SalesTarget::$assigned_target_value} capture the goal.
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

        $contractId = (int) $data['contract_id'];
        $mustSellUnits = (int) $data['must_sell_units_count'];
        if ($mustSellUnits < 1) {
            throw new \Exception('must_sell_units_count must be at least 1');
        }

        // Optional explicit monetary target; otherwise derived from project unit pricing.
        $explicitValue = array_key_exists('assigned_target_value', $data) && $data['assigned_target_value'] !== null && $data['assigned_target_value'] !== ''
            ? (float) $data['assigned_target_value']
            : null;

        $assignedValue = $this->resolveAssignedTargetValue($contractId, $mustSellUnits, $explicitValue);

        $target = SalesTarget::create([
            'leader_id' => $leader->id,
            'marketer_id' => $data['marketer_id'],
            'contract_id' => $contractId,
            'contract_unit_id' => null,
            'must_sell_units_count' => $mustSellUnits,
            'assigned_target_value' => $assignedValue,
            'target_type' => $data['target_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'leader_notes' => $data['leader_notes'] ?? null,
        ]);

        // Performance targets do not sync sales_target_units (no inventory assignment).

        return $target->fresh([
            'contract.city',
            'contract.district',
            'contractUnit',
            'contractUnits',
            'leader',
            'marketer',
        ]);
    }

    /**
     * Monetary target: explicit leader value, else sum of cheapest N unit prices in the project (or avg price × N).
     */
    public function resolveAssignedTargetValue(int $contractId, int $mustSellUnits, ?float $explicit): float
    {
        if ($explicit !== null && $explicit >= 0) {
            return round($explicit, 2);
        }

        $units = ContractUnit::query()
            ->where('contract_id', $contractId)
            ->orderBy('price')
            ->limit($mustSellUnits)
            ->get(['price']);

        if ($units->count() >= $mustSellUnits) {
            return round((float) $units->take($mustSellUnits)->sum('price'), 2);
        }

        $avg = (float) (ContractUnit::query()->where('contract_id', $contractId)->avg('price') ?? 0);

        return round($avg * $mustSellUnits, 2);
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

        return $target->fresh([
            'contract.city',
            'contract.district',
            'contractUnit',
            'contractUnits',
            'leader',
            'marketer',
        ]);
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
}
