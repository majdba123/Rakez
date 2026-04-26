<?php

namespace App\Services\Team;

use App\Models\TeamGroup;
use App\Models\TeamGroupLeader;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TeamGroupLeaderService
{
    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $perPage = (int) min(100, max(1, $perPage));

        $query = TeamGroupLeader::query()
            ->with(['user', 'teamGroup.team'])
            ->orderByDesc('id');

        if (! empty($filters['team_id'])) {
            $teamId = (int) $filters['team_id'];
            $query->whereHas('teamGroup', fn ($q) => $q->where('team_id', $teamId));
        }
        if (! empty($filters['team_group_id'])) {
            $query->where('team_group_id', (int) $filters['team_group_id']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (! empty($filters['search'])) {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', $search));
        }

        return $query->paginate($perPage);
    }

    public function assignLeader(int $teamGroupId, int $userId): TeamGroupLeader
    {
        return DB::transaction(function () use ($teamGroupId, $userId) {
            $group = TeamGroup::query()->findOrFail($teamGroupId);

            return TeamGroupLeader::query()->updateOrCreate(
                ['team_group_id' => $group->id],
                ['user_id' => $userId],
            )->load(['user', 'teamGroup.team']);
        });
    }

    /**
     * @return int Number of leader rows removed (0 if none)
     */
    public function removeLeader(int $teamGroupId): int
    {
        $group = TeamGroup::query()->findOrFail($teamGroupId);

        return TeamGroupLeader::query()->where('team_group_id', $group->id)->delete();
    }
}
