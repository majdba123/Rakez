<?php

namespace App\Services\Team;

use App\Models\Team;
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
}


