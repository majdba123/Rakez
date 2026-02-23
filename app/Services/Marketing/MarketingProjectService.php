<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\ContractInfo;

class MarketingProjectService
{
    /**
     * Contract status used for "completed" contracts in marketing context.
     * Requirements refer to "projects with completed contracts"; in this system
     * that is represented by contract status 'approved'.
     */
    public const COMPLETED_CONTRACT_STATUS = 'approved';

    /**
     * Get marketing projects whose contracts are completed (approved).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProjectsWithCompletedContracts(int $perPage = 15)
    {
        return MarketingProject::whereHas('contract', function ($query) {
                $query->where('status', self::COMPLETED_CONTRACT_STATUS);
            })
            ->with([
                'contract.info',
                'contract.projectMedia',
                'contract.secondPartyData',
                'contract.units',
                'teamLeader',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getProjectDetails($contractId)
    {
        return Contract::with([
            'info',
            'marketingProject.teams.user',
            'marketingProject.developerPlan',
            'marketingProject.employeePlans.user',
            'marketingProject.expectedBooking',
            'projectMedia',
            'units',
        ])->findOrFail($contractId);
    }

    /**
     * Get summary fields for a contract (location, contract_number, units_count, pricing, etc.)
     * for use in list and detail API responses.
     */
    public function getContractSummaryFields(Contract $contract): array
    {
        $contract->loadMissing(['info', 'units']);
        $info = $contract->info;
        $units = collect($contract->units ?? []);
        $availableUnits = $units->where('status', 'available');
        $pendingUnits = $units->where('status', 'pending');

        $locationParts = array_filter([$contract->city ?? null, $contract->district ?? null]);
        $location = $locationParts ? trim(implode(', ', $locationParts)) : null;

        return [
            'location' => $location,
            'city' => $contract->city ?? null,
            'district' => $contract->district ?? null,
            'contract_number' => $info?->contract_number ?? null,
            'units_count' => [
                'available' => $availableUnits->count(),
                'pending' => $pendingUnits->count(),
            ],
            'avg_unit_price' => $info ? (float) ($info->avg_property_value ?? 0) : 0,
            'commission_percent' => $info ? (float) ($info->commission_percent ?? 0) : 0,
            'total_available_value' => (float) $availableUnits->sum('price'),
            'advertiser_number' => (!empty($info?->agency_number)) ? 'Available' : 'Pending',
            'advertiser_number_value' => $info?->agency_number ?? null,
        ];
    }

    public function calculateCampaignBudget($contractId, $inputs)
    {
        $contract = Contract::with('info')->findOrFail($contractId);
        $info = $contract->info;

        $unitPrice = $inputs['unit_price'] ?? ($info->avg_property_value ?? 0);
        $commissionPercent = $info->commission_percent ?? 0;
        $marketingPercent = 10; // Fixed 10% as per requirements

        $commissionValue = $unitPrice * ($commissionPercent / 100);
        $marketingValue = $commissionValue * ($marketingPercent / 100);

        $durationDays = (int) ($info->agreement_duration_days ?? 30);
        $durationMonths = $this->resolveDurationMonths($info, $durationDays);

        return [
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'daily_budget' => $this->calculateDailyBudget($marketingValue, $durationDays),
            'monthly_budget' => $this->calculateMonthlyBudget($marketingValue, $durationMonths),
        ];
    }

    public function calculateDailyBudget($marketingValue, $durationDays)
    {
        return $durationDays > 0 ? $marketingValue / $durationDays : 0;
    }

    public function calculateMonthlyBudget($marketingValue, $durationMonths)
    {
        return $durationMonths > 0 ? $marketingValue / $durationMonths : 0;
    }

    public function getContractDurationStatus($contractId)
    {
        $contractInfo = ContractInfo::where('contract_id', $contractId)->first();
        if (!$contractInfo) return ['status' => 'unknown', 'days' => 0];

        $startDate = $contractInfo->created_at;
        $durationDays = $contractInfo->agreement_duration_days;
        $endDate = $startDate->copy()->addDays($durationDays);
        $remainingDays = (int) now()->diffInDays($endDate, false);

        if ($remainingDays < 30) {
            return [
                'status' => 'red',
                'label' => 'Less than 1 month remaining',
                'days' => $remainingDays
            ];
        } elseif ($remainingDays < 90) {
            return [
                'status' => 'orange',
                'label' => '1-3 months remaining',
                'days' => $remainingDays
            ];
        } else {
            return [
                'status' => 'green',
                'label' => 'More than 3 months remaining',
                'days' => $remainingDays
            ];
        }
    }

    private function resolveDurationMonths(?ContractInfo $info, int $durationDays): int
    {
        if ($info && !empty($info->agreement_duration_months)) {
            return max(1, (int) $info->agreement_duration_months);
        }

        if ($durationDays <= 0) {
            return 1;
        }

        return (int) ceil($durationDays / 30);
    }
}
