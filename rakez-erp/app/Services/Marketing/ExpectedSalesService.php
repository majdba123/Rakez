<?php

namespace App\Services\Marketing;

use App\Models\ExpectedBooking;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;

class ExpectedSalesService
{
    public function calculateExpectedBookings($directCommunications, $handRaises, $conversionRate)
    {
        $rate = $conversionRate ?: 1; // Default 1%
        return ($directCommunications + $handRaises) * ($rate / 100);
    }

    public function calculateExpectedBookingValue($projectId)
    {
        $project = MarketingProject::with('contract.info')->findOrFail($projectId);
        $avgValue = $project->contract->info->avg_property_value ?? 0;

        $expectedBooking = ExpectedBooking::where('marketing_project_id', $projectId)->first();
        $count = $expectedBooking ? $expectedBooking->expected_bookings_count : 0;

        return $count * $avgValue;
    }

    public function aggregateExpectedBookings()
    {
        return ExpectedBooking::sum('expected_bookings_count');
    }

    public function calculateDepositValuePerBooking($campaignBudget, $expectedBookings)
    {
        return $expectedBookings > 0 ? $campaignBudget / $expectedBookings : 0;
    }

    public function createOrUpdateExpectedBookings($projectId, $data)
    {
        $conversionRate = $data['conversion_rate'] ?? 1; // Default 1%
        $expectedCount = $this->calculateExpectedBookings(
            $data['direct_communications'] ?? 0,
            $data['hand_raises'] ?? 0,
            $conversionRate
        );

        return ExpectedBooking::updateOrCreate(
            ['marketing_project_id' => $projectId],
            [
                'direct_communications' => $data['direct_communications'] ?? 0,
                'hand_raises' => $data['hand_raises'] ?? 0,
                'expected_bookings_count' => $expectedCount,
                'conversion_rate' => $conversionRate,
                'expected_booking_value' => $this->calculateExpectedBookingValue($projectId)
            ]
        );
    }
}
