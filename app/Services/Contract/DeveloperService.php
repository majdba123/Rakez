<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\Team;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DeveloperService
{
    /**
     * Get paginated list of developers (unique by developer_number + developer_name)
     * with their projects, units count, and teams. Access-scoped like contract index.
     */
    public function getDevelopers(
        \Illuminate\Contracts\Auth\Authenticatable $user,
        ?string $search = null,
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator {
        $baseQuery = $this->buildContractBaseQuery($user, $search);

        $developerRows = $baseQuery
            ->select('developer_number', 'developer_name')
            ->groupBy('developer_number', 'developer_name')
            ->orderBy('developer_name')
            ->get();

        $total = $developerRows->count();
        $slice = $developerRows->slice(($page - 1) * $perPage, $perPage)->values();

        $data = $slice->map(function ($row) use ($user) {
            return $this->buildDeveloperItem(
                $row->developer_number,
                $row->developer_name,
                $user
            );
        });

        return new LengthAwarePaginator(
            $data,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Get one developer by developer_number (same shape as list item). Returns null if not found.
     */
    public function getDeveloperByNumber(string $developerNumber, \Illuminate\Contracts\Auth\Authenticatable $user): ?array
    {
        $developerNumber = trim($developerNumber);
        if ($developerNumber === '') {
            return null;
        }

        $baseQuery = $this->buildContractBaseQuery($user, null);
        $contract = $baseQuery
            ->where('developer_number', $developerNumber)
            ->select('developer_number', 'developer_name')
            ->first();

        if (!$contract) {
            return null;
        }

        return $this->buildDeveloperItem(
            $contract->developer_number,
            $contract->developer_name,
            $user
        );
    }

    /**
     * Base contract query with access control and optional search.
     */
    protected function buildContractBaseQuery(
        \Illuminate\Contracts\Auth\Authenticatable $user,
        ?string $search = null
    ): \Illuminate\Database\Eloquent\Builder {
        $query = Contract::query();

        if ($user->can('contracts.view_all')) {
            // no user filter
        } else {
            $query->where('user_id', $user->getAuthIdentifier());
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('developer_name', 'like', $term)
                    ->orWhere('developer_number', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * Build one developer item with projects, units_count, teams.
     */
    protected function buildDeveloperItem(
        string $developerNumber,
        string $developerName,
        \Illuminate\Contracts\Auth\Authenticatable $user
    ): array {
        $baseQuery = $this->buildContractBaseQuery($user, null);

        $contracts = $baseQuery
            ->where('developer_number', $developerNumber)
            ->where('developer_name', $developerName)
            ->with(['units', 'teams', 'user'])
            ->orderBy('project_name')
            ->get();

        // Use units() relation (ContractUnit count); Contract has 'units' JSON attribute so avoid $contract->units
        $projects = $contracts->map(function (Contract $contract) {
            $unitsCount = $contract->units()->count();
            return [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
                'status' => $contract->status,
                'city' => $contract->city,
                'district' => $contract->district,
                'units_count' => $unitsCount,
                'created_at' => $contract->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $allTeamIds = $contracts->pluck('id')->toArray();
        $teams = collect();
        if (!empty($allTeamIds)) {
            $teams = Team::query()
                ->whereHas('contracts', function ($q) use ($allTeamIds) {
                    $q->whereIn('contracts.id', $allTeamIds);
                })
                ->get()
                ->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name]);
        }

        $unitsCount = $contracts->sum(fn (Contract $c) => $c->units()->count());

        return [
            'developer_number' => $developerNumber,
            'developer_name' => $developerName,
            'projects_count' => $contracts->count(),
            'projects' => $projects,
            'units_count' => $unitsCount,
            'teams' => $teams->values()->all(),
        ];
    }
}
