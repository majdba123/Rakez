<?php

namespace App\Services\Team;

use App\Models\TeamGroup;
use App\Models\TeamGroupLeader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeamGroupService
{
    public function paginate(?int $teamId, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        $query = TeamGroup::query()->with(['team', 'teamGroupLeader.user'])->orderByDesc('id');
        if ($teamId !== null) {
            $query->where('team_id', $teamId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Sub-group leader rows (team_group_leaders) for all groups that belong to this team.
     *
     * @return LengthAwarePaginator<int, TeamGroupLeader>
     */
    public function paginateGroupLeadersForTeam(int $teamId, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        return TeamGroupLeader::query()
            ->whereHas('teamGroup', function ($q) use ($teamId) {
                $q->where('team_id', $teamId);
            })
            ->with(['user', 'teamGroup.team'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findByIdOrFail(int $id): TeamGroup
    {
        return TeamGroup::query()->with(['team', 'teamGroupLeader.user'])->findOrFail($id);
    }

    public function create(array $data): TeamGroup
    {
        return DB::transaction(function () use ($data) {
            return TeamGroup::query()->create([
                'team_id' => $data['team_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ])->load(['team', 'teamGroupLeader.user']);
        });
    }

    public function update(TeamGroup $group, array $data): TeamGroup
    {
        return DB::transaction(function () use ($group, $data) {
            if (array_key_exists('team_id', $data)) {
                $oldTeamId = $group->team_id;
                $group->team_id = $data['team_id'];
                if ((int) $oldTeamId !== (int) $data['team_id']) {
                    TeamGroupLeader::query()->where('team_group_id', $group->id)->delete();
                }
            }
            if (array_key_exists('name', $data)) {
                $group->name = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $group->description = $data['description'];
            }
            $group->save();

            return $group->fresh()->load(['team', 'teamGroupLeader.user']);
        });
    }

    public function delete(TeamGroup $group): void
    {
        $group->delete();
    }
}
