<?php

namespace App\Services\Marketing;

use App\Models\MarketingBudgetDistribution;
use App\Models\MarketingProject;

class MarketingBudgetCalculationService
{
    /**
     * Calculate budget for each platform based on total budget and platform distribution percentages
     *
     * @param float $totalBudget
     * @param array $platformDistribution Array of platform => percentage
     * @return array Array of platform => budget
     */
    public function calculatePlatformBudgets(float $totalBudget, array $platformDistribution): array
    {
        $platformBudgets = [];

        foreach ($platformDistribution as $platform => $percentage) {
            $platformBudgets[$platform] = $totalBudget * ($percentage / 100);
        }

        return $platformBudgets;
    }

    /**
     * Calculate budget for each objective (Impression, Lead, Direct Contact) within a platform
     *
     * @param float $platformBudget
     * @param array $objectives Array with impression_percent, lead_percent, direct_contact_percent
     * @return array Array with impression, lead, direct_contact budgets
     */
    public function calculateObjectiveBudgets(float $platformBudget, array $objectives): array
    {
        return [
            'impression' => $platformBudget * (($objectives['impression_percent'] ?? 0) / 100),
            'lead' => $platformBudget * (($objectives['lead_percent'] ?? 0) / 100),
            'direct_contact' => $platformBudget * (($objectives['direct_contact_percent'] ?? 0) / 100),
        ];
    }

    /**
     * Calculate number of leads based on leads budget and CPL
     *
     * @param float $leadsBudget
     * @param float $cpl Cost per lead
     * @return int Number of leads
     */
    public function calculateLeadsCount(float $leadsBudget, float $cpl): int
    {
        if ($cpl <= 0) {
            return 0;
        }

        return (int) floor($leadsBudget / $cpl);
    }

    /**
     * Calculate number of direct contacts based on direct contact budget and cost per contact
     *
     * @param float $directContactBudget
     * @param float $directContactCost Cost per direct contact
     * @return int Number of direct contacts
     */
    public function calculateDirectContactsCount(float $directContactBudget, float $directContactCost): int
    {
        if ($directContactCost <= 0) {
            return 0;
        }

        return (int) floor($directContactBudget / $directContactCost);
    }

    /**
     * Calculate total opportunities (leads + direct contacts)
     *
     * @param int $leadsCount
     * @param int $directContactsCount
     * @return int Total opportunities
     */
    public function calculateTotalOpportunities(int $leadsCount, int $directContactsCount): int
    {
        return $leadsCount + $directContactsCount;
    }

    /**
     * Calculate expected bookings based on total opportunities and conversion rate
     *
     * @param int $totalOpportunities
     * @param float $conversionRate Conversion rate percentage
     * @return float Expected bookings count
     */
    public function calculateExpectedBookings(int $totalOpportunities, float $conversionRate): float
    {
        return $totalOpportunities * ($conversionRate / 100);
    }

    /**
     * Calculate expected revenue based on bookings count and average booking value
     *
     * @param float $bookingsCount
     * @param float $bookingValue Average booking value
     * @return float Expected revenue
     */
    public function calculateExpectedRevenue(float $bookingsCount, float $bookingValue): float
    {
        return $bookingsCount * $bookingValue;
    }

    /**
     * Calculate cost per booking
     *
     * @param float $totalBudget
     * @param float $bookingsCount
     * @return float Cost per booking
     */
    public function calculateCostPerBooking(float $totalBudget, float $bookingsCount): float
    {
        if ($bookingsCount <= 0) {
            return 0;
        }

        return $totalBudget / $bookingsCount;
    }

    /**
     * Calculate all metrics for a budget distribution
     *
     * @param MarketingBudgetDistribution $budgetDistribution
     * @return array Complete calculation results
     */
    public function calculateAll(MarketingBudgetDistribution $budgetDistribution): array
    {
        $totalBudget = $budgetDistribution->total_budget;
        $platformDistribution = $budgetDistribution->platform_distribution ?? [];
        $platformObjectives = $budgetDistribution->platform_objectives ?? [];
        $platformCosts = $budgetDistribution->platform_costs ?? [];
        $conversionRate = $budgetDistribution->conversion_rate;
        $averageBookingValue = $budgetDistribution->average_booking_value;

        // Step 1: Calculate platform budgets
        $platformBudgets = $this->calculatePlatformBudgets($totalBudget, $platformDistribution);

        // Step 2: Calculate objective budgets for each platform
        $objectiveBudgets = [];
        $leadsCount = [];
        $directContactsCount = [];
        $totalLeadsCount = 0;
        $totalDirectContactsCount = 0;

        foreach ($platformBudgets as $platform => $platformBudget) {
            // Calculate objective budgets
            $objectives = $platformObjectives[$platform] ?? [];
            $objectiveBudgets[$platform] = $this->calculateObjectiveBudgets($platformBudget, $objectives);

            // Calculate leads and direct contacts counts
            $platformCost = $platformCosts[$platform] ?? [];
            $cpl = $platformCost['cpl'] ?? 0;
            $directContactCost = $platformCost['direct_contact_cost'] ?? 0;

            $leadsBudget = $objectiveBudgets[$platform]['lead'] ?? 0;
            $directContactBudget = $objectiveBudgets[$platform]['direct_contact'] ?? 0;

            $platformLeadsCount = $this->calculateLeadsCount($leadsBudget, $cpl);
            $platformDirectContactsCount = $this->calculateDirectContactsCount($directContactBudget, $directContactCost);

            $leadsCount[$platform] = $platformLeadsCount;
            $directContactsCount[$platform] = $platformDirectContactsCount;

            $totalLeadsCount += $platformLeadsCount;
            $totalDirectContactsCount += $platformDirectContactsCount;
        }

        // Step 3: Calculate total opportunities
        $totalOpportunities = $this->calculateTotalOpportunities($totalLeadsCount, $totalDirectContactsCount);

        // Step 4: Calculate expected bookings
        $expectedBookings = $this->calculateExpectedBookings($totalOpportunities, $conversionRate);

        // Step 5: Calculate expected revenue
        $expectedRevenue = $this->calculateExpectedRevenue($expectedBookings, $averageBookingValue);

        // Step 6: Calculate cost per booking
        $costPerBooking = $this->calculateCostPerBooking($totalBudget, $expectedBookings);

        return [
            'platform_budgets' => $platformBudgets,
            'objective_budgets' => $objectiveBudgets,
            'leads_count' => $leadsCount,
            'direct_contacts_count' => $directContactsCount,
            'total_leads_count' => $totalLeadsCount,
            'total_direct_contacts_count' => $totalDirectContactsCount,
            'total_opportunities' => $totalOpportunities,
            'expected_bookings' => round($expectedBookings, 2),
            'expected_revenue' => round($expectedRevenue, 2),
            'cost_per_booking' => round($costPerBooking, 2),
        ];
    }

    /**
     * Save or update budget distribution and calculate results
     *
     * @param int $projectId
     * @param string $planType 'employee' or 'developer'
     * @param array $data
     * @return MarketingBudgetDistribution
     */
    public function saveOrUpdateDistribution(int $projectId, string $planType, array $data): MarketingBudgetDistribution
    {
        // Validate plan type
        if (!in_array($planType, ['employee', 'developer'])) {
            throw new \InvalidArgumentException('Invalid plan type. Must be "employee" or "developer".');
        }

        // Get or find the related plan ID
        $employeePlanId = null;
        $developerPlanId = null;

        if ($planType === 'employee') {
            $employeePlanId = $data['employee_marketing_plan_id'] ?? null;
        } else {
            $developerPlanId = $data['developer_marketing_plan_id'] ?? null;
        }

        // Validate platform distribution percentages sum to 100%
        $platformDistribution = $data['platform_distribution'] ?? [];
        $platformSum = array_sum($platformDistribution);
        if (abs($platformSum - 100) > 0.01) {
            throw new \InvalidArgumentException('Platform distribution percentages must sum to 100%. Current sum: ' . $platformSum);
        }

        // Validate platform objectives sum to 100% for each platform
        $platformObjectives = $data['platform_objectives'] ?? [];
        foreach ($platformObjectives as $platform => $objectives) {
            $objectiveSum = ($objectives['impression_percent'] ?? 0) +
                           ($objectives['lead_percent'] ?? 0) +
                           ($objectives['direct_contact_percent'] ?? 0);
            if (abs($objectiveSum - 100) > 0.01) {
                throw new \InvalidArgumentException("Platform objectives for {$platform} must sum to 100%. Current sum: " . $objectiveSum);
            }
        }

        // Create or update the distribution
        $distribution = MarketingBudgetDistribution::updateOrCreate(
            [
                'marketing_project_id' => $projectId,
                'plan_type' => $planType,
            ],
            [
                'employee_marketing_plan_id' => $employeePlanId,
                'developer_marketing_plan_id' => $developerPlanId,
                'total_budget' => $data['total_budget'],
                'platform_distribution' => $platformDistribution,
                'platform_objectives' => $platformObjectives,
                'platform_costs' => $data['platform_costs'] ?? [],
                'cost_source' => $data['cost_source'] ?? [],
                'conversion_rate' => $data['conversion_rate'],
                'average_booking_value' => $data['average_booking_value'],
            ]
        );

        // Calculate and save results
        $calculatedResults = $this->calculateAll($distribution);
        $distribution->calculated_results = $calculatedResults;
        $distribution->save();

        return $distribution->fresh();
    }
}
