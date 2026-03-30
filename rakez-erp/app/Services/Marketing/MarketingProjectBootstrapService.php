<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use App\Models\MarketingProject;
use Illuminate\Support\Collection;

class MarketingProjectBootstrapService
{
    public const DEFAULT_STATUS = 'active';

    public function ensureForCompletedContract(Contract $contract): ?MarketingProject
    {
        $existing = MarketingProject::where('contract_id', $contract->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($contract->status !== MarketingProjectService::COMPLETED_CONTRACT_STATUS) {
            return null;
        }

        $project = new MarketingProject([
            'contract_id' => $contract->id,
            'status' => self::DEFAULT_STATUS,
        ]);

        // Keep project timestamps aligned with the contract when bootstrapping lazily.
        $project->created_at = $contract->created_at;
        $project->updated_at = $contract->updated_at;
        $project->save();

        return $project;
    }

    public function ensureForContracts(iterable $contracts): void
    {
        foreach ($contracts as $contract) {
            if ($contract instanceof Contract) {
                $this->ensureForCompletedContract($contract);
            }
        }
    }

    public function getProjectsByContractIds(iterable $contractIds): Collection
    {
        $ids = collect($contractIds)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MarketingProject::with('teamLeader')
            ->whereIn('contract_id', $ids)
            ->orderBy('id')
            ->get()
            ->groupBy('contract_id')
            ->map(fn (Collection $projects) => $projects->first());
    }
}
