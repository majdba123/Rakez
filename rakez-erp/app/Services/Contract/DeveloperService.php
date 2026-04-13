<?php

namespace App\Services\Contract;

use App\Models\Contract;
use App\Models\Team;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DeveloperService
{
    /**
     * Get paginated list of developers (unique by developer_number + developer_name)
     * with their projects, units count, and teams. Access-scoped like contract index.
     */
    public function getDevelopers(
        Authenticatable $user,
        ?string $search = null,
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator {
        $baseQuery = $this->buildContractBaseQuery($user, $search);

        $developerRows = $baseQuery
            ->selectRaw('MIN(contracts.id) as id, developer_number, developer_name')
            ->groupBy('developer_number', 'developer_name')
            ->orderBy('developer_name')
            ->get();

        $total = $developerRows->count();
        $slice = $developerRows->slice(($page - 1) * $perPage, $perPage)->values();

        $data = $slice->map(function ($row) use ($user) {
            return $this->buildDeveloperItem(
                $row->developer_number,
                $row->developer_name,
                $user,
                (int) $row->id
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
    public function getDeveloperByNumber(string $developerNumber, Authenticatable $user): ?array
    {
        $developerNumber = trim($developerNumber);
        if ($developerNumber === '') {
            return null;
        }

        // Normalize: frontend may send "+" as "(" (e.g. %28) in URL path.
        $developerNumber = str_replace('(', '+', $developerNumber);

        $baseQuery = $this->buildContractBaseQuery($user, null);

        $candidateNumbers = [$developerNumber];
        if (!str_starts_with($developerNumber, '+')) {
            $candidateNumbers[] = '+' . $developerNumber;
        } else {
            $candidateNumbers[] = ltrim($developerNumber, '+');
        }

        foreach (array_unique($candidateNumbers) as $candidateNumber) {
            $contract = (clone $baseQuery)
                ->where('developer_number', $candidateNumber)
                ->select('id', 'developer_number', 'developer_name')
                ->first();

            if ($contract) {
                return $this->buildDeveloperItem(
                    $contract->developer_number,
                    $contract->developer_name,
                    $user,
                    (int) $contract->id
                );
            }
        }

        return null;
    }

    /**
     * Get one developer by contract id (representative id for this developer). Same shape as getDeveloperByNumber.
     */
    public function getDeveloperById(int $contractId, Authenticatable $user): ?array
    {
        $contract = $this->buildContractBaseQuery($user, null)
            ->where('contracts.id', $contractId)
            ->select('id', 'developer_number', 'developer_name')
            ->first();

        if (!$contract) {
            return null;
        }

        return $this->buildDeveloperItem(
            $contract->developer_number,
            $contract->developer_name,
            $user,
            (int) $contract->id
        );
    }

    /**
     * Base contract query with access control and optional search.
     */
    protected function buildContractBaseQuery(
        Authenticatable $user,
        ?string $search = null
    ): Builder {
        $query = Contract::query();

        if (!$user->can('contracts.view_all')) {
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
        Authenticatable $user,
        ?int $representativeId = null
    ): array {
        $contracts = $this->buildContractBaseQuery($user, null)
            ->where('developer_number', $developerNumber)
            ->where('developer_name', $developerName)
            ->with(['contractUnits', 'teams', 'user', 'city', 'district'])
            ->orderBy('project_name')
            ->get();

        $projects = $contracts->map(function (Contract $contract) {
            $unitsCount = $contract->contractUnits->count();

            return [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
                'status' => $contract->status,
                'city' => $contract->city?->name,
                'district' => $contract->district?->name,
                'units_count' => $unitsCount,
                'created_at' => $contract->created_at?->toIso8601String(),
            ];
        })->values()->all();

        $contractIds = $contracts->pluck('id')->all();

        $teams = empty($contractIds)
            ? collect()
            : Team::query()
                ->whereHas('contracts', function ($q) use ($contractIds) {
                    $q->whereIn('contracts.id', $contractIds);
                })
                ->get()
                ->map(fn (Team $team) => ['id' => $team->id, 'name' => $team->name]);

        $unitsCount = $contracts->sum(fn (Contract $contract) => $contract->contractUnits->count());

        $item = [
            'developer_number' => $developerNumber,
            'developer_name' => $developerName,
            'projects_count' => $contracts->count(),
            'projects' => $projects,
            'units_count' => $unitsCount,
            'teams' => $teams->values()->all(),
        ];

        if ($representativeId !== null) {
            $item['id'] = $representativeId;
        }

        return $item;
    }
}
