<?php

namespace App\Services\Marketing;

use App\Models\ExpectedBooking;
use App\Models\MarketingProject;

class ExpectedSalesService
{
    public function __construct(
        private ContractPricingBasisService $pricingBasisService
    ) {}

    public function calculateExpectedBookings($directCommunications, $handRaises, $conversionRate)
    {
        $rate = $conversionRate ?: 1; // Default 1%

        return ($directCommunications + $handRaises) * ($rate / 100);
    }

    /**
     * Expected booking value uses project-wide average unit price when unit rows exist; else stored avg_property_value.
     */
    public function calculateExpectedBookingValue($projectId, $expectedBookingsCount = null)
    {
        $project = MarketingProject::with(['contract.info', 'contract.contractUnits'])->findOrFail($projectId);
        $contract = $project->contract;
        $contract->loadMissing(['info', 'contractUnits']);
        $basis = $this->pricingBasisService->resolve($contract, []);

        $avgPerUnit = (float) ($basis['average_unit_price_all'] ?? 0);
        if ($avgPerUnit <= 0) {
            $avgPerUnit = (float) ($contract->info->avg_property_value ?? 0);
        }

        $count = $expectedBookingsCount;
        if ($count === null) {
            $expectedBooking = ExpectedBooking::where('marketing_project_id', $projectId)->first();
            $count = $expectedBooking ? $expectedBooking->expected_bookings_count : 0;
        }

        return $count * $avgPerUnit;
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

        $expectedBookingValue = $this->calculateExpectedBookingValue($projectId, $expectedCount);

        return ExpectedBooking::updateOrCreate(
            ['marketing_project_id' => $projectId],
            [
                'direct_communications' => $data['direct_communications'] ?? 0,
                'hand_raises' => $data['hand_raises'] ?? 0,
                'expected_bookings_count' => $expectedCount,
                'conversion_rate' => $conversionRate,
                'expected_booking_value' => $expectedBookingValue,
            ]
        );
    }
}
