<?php

namespace App\Services\Team;

use App\Models\Team;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Support\Query\SearchTerm;
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
                    $q->where('name', 'like', SearchTerm::contains($search))
                        ->orWhere('description', 'like', SearchTerm::contains($search));
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
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

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

    public function updateTeam(int $id, array $data): Team
    {
        DB::beginTransaction();
        try {
            $team = Team::findOrFail($id);

            $updateData = [];
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


        // Units for this team (via contracts -> second_party_data -> contract_units)
        $baseUnitsQuery = DB::table('contract_units')
            ->join('second_party_data', 'second_party_data.id', '=', 'contract_units.second_party_data_id')
            ->join('contracts', 'contracts.id', '=', 'second_party_data.contract_id')
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

