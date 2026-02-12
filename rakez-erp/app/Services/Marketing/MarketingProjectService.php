<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\ContractInfo;

class MarketingProjectService
{
    /**
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getProjectsWithCompletedContracts(int $perPage = 15)
    {
        return MarketingProject::whereHas('contract', function ($query) {
                $query->where('status', 'approved');
            })
            ->with([
                'contract.info',
                'contract.projectMedia',
                'contract.secondPartyData',
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
            'projectMedia'
        ])->findOrFail($contractId);
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
