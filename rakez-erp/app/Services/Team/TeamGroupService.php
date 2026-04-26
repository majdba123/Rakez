<?php

namespace App\Services\Team;

use App\Models\TeamGroup;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeamGroupService
{
    public function paginate(?int $teamId, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        $query = TeamGroup::query()->with('team')->orderByDesc('id');
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->paginate($perPage);
    }

    public function findByIdOrFail(int $id): TeamGroup
    {
        return TeamGroup::query()->with('team')->findOrFail($id);
    }

    public function create(array $data): TeamGroup
    {
        return DB::transaction(function () use ($data) {
            return TeamGroup::query()->create([
                'team_id' => $data['team_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ])->load('team');
        });
    }

    public function update(TeamGroup $group, array $data): TeamGroup
    {
        return DB::transaction(function () use ($group, $data) {
            if (array_key_exists('team_id', $data)) {
                $group->team_id = $data['team_id'];
            }
            if (array_key_exists('name', $data)) {
                $group->name = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $group->description = $data['description'];
            }
            $group->save();

            return $group->fresh()->load('team');
        });
    }

    public function delete(TeamGroup $group): void
    {
        $group->delete();
    }
}
