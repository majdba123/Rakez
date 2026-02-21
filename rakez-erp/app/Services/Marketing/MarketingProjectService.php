<?php

namespace App\Services\Marketing;

use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\ContractInfo;

class MarketingProjectService
{
    public function getProjectsWithCompletedContracts()
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
            ->get();
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

        $durationDays = $info->agreement_duration_days ?? 30;

        return [
            'commission_value' => $commissionValue,
            'marketing_value' => $marketingValue,
            'daily_budget' => $this->calculateDailyBudget($marketingValue, $durationDays),
            'monthly_budget' => $this->calculateMonthlyBudget($marketingValue, $durationDays / 30),
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
        } elseif ($remainingDays <= 90) {
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
}
