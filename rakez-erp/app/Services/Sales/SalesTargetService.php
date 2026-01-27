<?php

namespace App\Services\Sales;

use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesTargetService
{
    /**
     * Get targets for a marketer.
     */
    public function getMyTargets(User $marketer, array $filters): LengthAwarePaginator
    {
        $query = SalesTarget::where('marketer_id', $marketer->id)
            ->with(['contract', 'contractUnit', 'leader']);

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
     * Create a target (leader only).
     */
    public function createTarget(array $data, User $leader): SalesTarget
    {
        // Validate leader has access to this project
        if (!$this->leaderHasAccessToProject($leader, $data['contract_id'])) {
            throw new \Exception('Leader is not assigned to this project');
        }

        // Validate marketer is in same team
        $marketer = User::findOrFail($data['marketer_id']);
        if ($marketer->team !== $leader->team) {
            throw new \Exception('Marketer must be in the same team as leader');
        }

        return SalesTarget::create([
            'leader_id' => $leader->id,
            'marketer_id' => $data['marketer_id'],
            'contract_id' => $data['contract_id'],
            'contract_unit_id' => $data['contract_unit_id'] ?? null,
            'target_type' => $data['target_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'leader_notes' => $data['leader_notes'] ?? null,
        ]);
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
