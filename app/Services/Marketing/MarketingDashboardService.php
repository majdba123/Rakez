<?php

namespace App\Services\Marketing;

use App\Models\Lead;
use App\Models\ContractUnit;
use App\Models\MarketingTask;
use App\Models\DailyDeposit;
use App\Models\ExpectedBooking;
use App\Models\MarketingBudgetDistribution;
use App\Models\MarketingProject;
use App\Models\DailyMarketingSpend;

class MarketingDashboardService
{
    public function getDashboardKPIs()
    {
        $expectedSalesService = new ExpectedSalesService();
        return [
            'total_leads' => $this->getTotalLeads(),
            'available_units_value' => $this->getAvailableUnitsValue(),
            'available_units_count' => $this->getAvailableUnitsCount(),
            'daily_task_achievement_rate' => $this->getDailyTaskAchievementRate(),
            'daily_deposits_count' => $this->getDailyDepositsCount(),
            'deposit_cost' => $this->getDepositCost(),
            'total_expected_bookings' => $expectedSalesService->aggregateExpectedBookings(),
            'total_expected_booking_value' => ExpectedBooking::sum('expected_booking_value'),
            'deposit_value_per_booking' => $this->getDepositValuePerBooking(),
        ];
    }

    public function getTotalLeads()
    {
        return Lead::count();
    }

    public function getAvailableUnitsValue()
    {
        return ContractUnit::where('status', 'available')->sum('price');
    }

    public function getAvailableUnitsCount()
    {
        return ContractUnit::where('status', 'available')->count();
    }

    public function getDailyTaskAchievementRate($date = null)
    {
        $date = $date ?: now()->toDateString();

        $totalTasks = MarketingTask::whereDate('created_at', $date)->count();
        if ($totalTasks === 0) return 0;

        $completedTasks = MarketingTask::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->count();

        return ($completedTasks / $totalTasks) * 100;
    }

    public function getDailyDepositsCount($date = null)
    {
        $date = $date ?: now()->toDateString();
        return DailyDeposit::whereDate('date', $date)->count();
    }

    public function getDepositCost($date = null)
    {
        $date = $date ?: now()->toDateString();

        $dailySpend = DailyMarketingSpend::whereDate('date', $date)->sum('amount');
        $dailyDeposits = DailyDeposit::whereDate('date', $date)->count();

        if ($dailyDeposits === 0) return 0;

        return $dailySpend / $dailyDeposits;
    }

    /**
     * Deposit value per booking = Total campaign budget ÷ Number of expected bookings.
     */
    public function getDepositValuePerBooking(): float
    {
        $totalExpectedBookings = ExpectedBooking::sum('expected_bookings_count');
        if ($totalExpectedBookings <= 0) {
            return 0.0;
        }
        $totalCampaignBudget = MarketingBudgetDistribution::sum('total_budget');

        return (float) ($totalCampaignBudget / $totalExpectedBookings);
    }
}
