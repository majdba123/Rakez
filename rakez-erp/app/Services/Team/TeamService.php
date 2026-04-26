<?php

namespace App\Services\Team;

use App\Models\Team;
use App\Models\TeamGroup;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

class TeamService
{
    /**
     * Get teams for sales user with filters and pagination.
     */
    public function getTeams(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = Team::query();

            // Search (name/description)
            if (isset($filters['search']) && $filters['search'] !== null && $filters['search'] !== '') {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . addslashes($search) . '%')
                        ->orWhere('description', 'like', '%' . addslashes($search) . '%');
                });
            }

            // Status filter: active / deleted
            if (isset($filters['status']) && $filters['status']) {
                if ($filters['status'] === 'deleted') {
                    $query->onlyTrashed();
                }
            }

            // Sorting (whitelist)
            $allowedSortFields = ['id', 'name', 'created_at', 'updated_at'];
            $sortField = $filters['sort_by'] ?? 'created_at';
            $sortOrder = strtolower($filters['sort_order'] ?? 'desc');

            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'created_at';
            }
            if (!in_array($sortOrder, ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }

            $query->orderBy($sortField, $sortOrder);

            return $query->paginate($perPage);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch teams: ' . $e->getMessage());
        }
    }

    public function storeTeam(array $data, int $userId): Team
    {
        DB::beginTransaction();
        try {
            $team = Team::create([
                'code' => $data['code'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);
            if ($team->code === null || $team->code === '') {
                $team->forceFill([
                    'code' => 'T' . str_pad((string) $team->id, 6, '0', STR_PAD_LEFT),
                ])->save();
            }

            DB::commit();
            return $team;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create team: ' . $e->getMessage());
        }
    }

    public function getTeamById(int $id): Team
    {
        try {
            return Team::findOrFail($id);
        } catch (Exception $e) {
            throw new Exception('Team not found or unauthorized: ' . $e->getMessage());
        }
    }

    /**
     * Users assigned to this team (users.team_id). Optionally filter to one team sub-group
     * (users.team_group_id) — group must belong to this team.
     */
    public function getTeamMembers(int $teamId, int $perPage = 15, ?int $teamGroupId = null): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        $query = User::query()
            ->where('team_id', $teamId)
            ->orderBy('name');

        if ($teamGroupId !== null) {
            $group = TeamGroup::query()
                ->where('id', $teamGroupId)
                ->where('team_id', $teamId)
                ->first();
            if (! $group) {
                throw new Exception('المجموعة غير موجودة أو لا تتبع هذا الفريق.');
            }
            $query->where('team_group_id', $teamGroupId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Sales users not assigned to any team (team_id IS NULL). Same pool allowed by assignSalesMemberToTeamGroup.
     */
    public function getSalesUsersWithoutTeam(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        $query = User::query()
            ->where('type', 'sales')
            ->whereNull('team_id');

        if ($search !== null && $search !== '') {
            $term = '%' . addslashes($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function updateTeam(int $id, array $data): Team
    {
        DB::beginTransaction();
        try {
            $team = Team::findOrFail($id);

            $updateData = [];
            if (array_key_exists('code', $data)) {
                $updateData['code'] = $data['code'];
            }
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $updateData['description'] = $data['description'];
            }

            if (!empty($updateData)) {
                $team->update($updateData);
            }

            DB::commit();
            return $team->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update team: ' . $e->getMessage());
        }
    }

    public function deleteTeam(int $id): bool
    {
        DB::beginTransaction();
        try {
            $team = Team::findOrFail($id);
            $team->delete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete team: ' . $e->getMessage());
        }
    }

    /**
     * Assign a sales user to a team **group** under the given team; syncs users.team_id from the group.
     */
    public function assignSalesMemberToTeamGroup(int $teamId, int $teamGroupId, int $userId): User
    {
        DB::beginTransaction();
        try {
            Team::findOrFail($teamId);
            $group = TeamGroup::query()
                ->where('id', $teamGroupId)
                ->where('team_id', $teamId)
                ->firstOrFail();
            $user = User::findOrFail($userId);

            if ($user->type !== 'sales') {
                throw new Exception('يمكن إضافة موظفي المبيعات فقط (نوع المستخدم: sales).');
            }

            $user->update([
                'team_group_id' => $group->id,
                'team_id' => $group->team_id,
            ]);

            DB::commit();

            return $user->fresh(['team', 'teamGroup']);
        } catch (Exception $e) {
            DB::rollBack();
            if (str_contains($e->getMessage(), 'يمكن إضافة')) {
                throw $e;
            }
            throw new Exception('Failed to assign team member: ' . $e->getMessage());
        }
    }

    /**
     * Remove a user from a team (clears both team and team group assignment).
     */
    public function removeMemberFromTeam(int $teamId, int $userId): void
    {
        DB::beginTransaction();
        try {
            Team::findOrFail($teamId);
            $user = User::query()->where('id', $userId)->where('team_id', $teamId)->firstOrFail();
            $user->update([
                'team_id' => null,
                'team_group_id' => null,
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to remove team member: ' . $e->getMessage());
        }
    }

    /**
     * Set team + group from a team_group id, or clear both when null.
     */
    public function setUserTeamGroup(User $user, ?int $teamGroupId): void
    {
        if ($teamGroupId === null) {
            $user->update([
                'team_group_id' => null,
                'team_id' => null,
            ]);

            return;
        }

        $group = TeamGroup::query()->findOrFail($teamGroupId);
        $user->update([
            'team_group_id' => $group->id,
            'team_id' => $group->team_id,
        ]);
    }

    /**
     * HR analytics: "average sales by team"
     * Defined as: sold_units_for_team / sales_employees_in_team
     */
    public function getSalesAverageByTeam(int $teamId): array
    {
        $team = Team::findOrFail($teamId);

        // Employees in this team (type = sales)
        $salesEmployeesCount = DB::table('users')
            ->where('team_id', $teamId)
            ->where('type', 'sales')
            ->count();


        // Units for this team (via contracts.contract_id on contract_units)
        $baseUnitsQuery = DB::table('contract_units')
            ->join('contracts', 'contracts.id', '=', 'contract_units.contract_id')
            ->join('contract_team', 'contract_team.contract_id', '=', 'contracts.id')
            ->where('contract_team.team_id', $teamId);

        $totalUnits = (clone $baseUnitsQuery)->count();
        $soldUnits = (clone $baseUnitsQuery)->where('contract_units.status', 'sold')->count();

        $soldUnitsPerSalesEmployee = $salesEmployeesCount > 0 ? ($soldUnits / $salesEmployeesCount) : 0.0;

        return [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'employees' => [
                'sales_employees_count' => $salesEmployeesCount,
            ],
            'units' => [
                'total_units' => $totalUnits,
                'sold_units' => $soldUnits,
            ],
            'average_sales' => [
                'sold_units_per_sales_employee' => $soldUnitsPerSalesEmployee,
            ],
        ];
    }
}


