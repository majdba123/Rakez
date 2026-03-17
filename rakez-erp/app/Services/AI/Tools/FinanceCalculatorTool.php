<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

class FinanceCalculatorTool implements ToolContract
{
    public function __construct(
        private readonly NumericGuardrails $guardrails,
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $type = $args['calculation_type'] ?? 'mortgage';

        $data = match ($type) {
            'mortgage' => $this->calculateMortgage($args),
            'commission' => $this->calculateCommission($args),
            'romi' => $this->calculateRomi($args),
            'project_roi' => $this->calculateProjectRoi($args),
            'payment_plan' => $this->calculatePaymentPlan($args),
            default => ['error' => "Unknown calculation type: {$type}"],
        };

        $response = ToolResponse::success('tool_finance_calculator', $args, $data, [
            ['type' => 'tool', 'title' => "Finance: {$type}", 'ref' => "finance:{$type}"],
        ]);

        // Apply guardrails
        if ($type === 'romi' && isset($data['romi_percent'])) {
            $check = $this->guardrails->validateROI($data['romi_percent'], 'romi');
            if (! $check->isOk()) {
                $response = ToolResponse::withGuardrails($response, $check);
            }
        }

        if ($type === 'project_roi' && isset($data['roi_percent'])) {
            $check = $this->guardrails->validateROI($data['roi_percent'], 'project_roi');
            if (! $check->isOk()) {
                $response = ToolResponse::withGuardrails($response, $check);
            }
        }

        if ($type === 'mortgage' && isset($data['dti_percent'])) {
            $check = $this->guardrails->validateDTI($data['dti_percent']);
            if (! $check->isOk()) {
                $response = ToolResponse::withGuardrails($response, $check);
            }
        }

        return $response;
    }

    private function calculateMortgage(array $args): array
    {
        $price = $args['unit_price'] ?? 0;
        $downPaymentPercent = $args['down_payment_percent'] ?? 10;
        $annualRate = $args['annual_rate'] ?? 5.5;
        $years = $args['years'] ?? 25;

        $downPayment = $price * ($downPaymentPercent / 100);
        $loanAmount = $price - $downPayment;
        $monthlyRate = ($annualRate / 100) / 12;
        $months = $years * 12;

        // Monthly payment using amortization formula
        if ($monthlyRate > 0) {
            $monthlyPayment = $loanAmount * ($monthlyRate * pow(1 + $monthlyRate, $months))
                / (pow(1 + $monthlyRate, $months) - 1);
        } else {
            $monthlyPayment = $loanAmount / $months;
        }

        $totalPayment = $monthlyPayment * $months;
        $totalInterest = $totalPayment - $loanAmount;

        return [
            'unit_price' => $price,
            'down_payment' => round($downPayment, 2),
            'down_payment_percent' => $downPaymentPercent,
            'loan_amount' => round($loanAmount, 2),
            'annual_rate' => $annualRate,
            'years' => $years,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2),
        ];
    }

    private function calculateCommission(array $args): array
    {
        $salePrice = $args['sale_price'] ?? 0;
        $commissionRate = $args['commission_rate'] ?? 2.5;
        $agentCount = $args['agent_count'] ?? 1;
        $leaderSharePercent = $args['leader_share_percent'] ?? 0;

        $totalCommission = $salePrice * ($commissionRate / 100);
        $leaderShare = $totalCommission * ($leaderSharePercent / 100);
        $remainingForAgents = $totalCommission - $leaderShare;
        $perAgent = $agentCount > 0 ? $remainingForAgents / $agentCount : 0;

        return [
            'sale_price' => $salePrice,
            'commission_rate' => $commissionRate,
            'total_commission' => round($totalCommission, 2),
            'leader_share' => round($leaderShare, 2),
            'per_agent' => round($perAgent, 2),
            'agent_count' => $agentCount,
        ];
    }

    private function calculateRomi(array $args): array
    {
        $marketingSpend = $args['marketing_spend'] ?? 0;
        $soldUnits = $args['sold_units'] ?? 0;
        $avgUnitPrice = $args['avg_unit_price'] ?? 0;
        $commissionRate = $args['commission_rate'] ?? 2.5;

        $revenue = $soldUnits * $avgUnitPrice * ($commissionRate / 100);
        $romi = $marketingSpend > 0 ? (($revenue - $marketingSpend) / $marketingSpend) * 100 : 0;

        return [
            'marketing_spend' => $marketingSpend,
            'commission_revenue' => round($revenue, 2),
            'romi_percent' => round($romi, 2),
            'net_profit' => round($revenue - $marketingSpend, 2),
            'roi_label' => 'ROMI (عائد الاستثمار التسويقي)',
        ];
    }

    private function calculateProjectRoi(array $args): array
    {
        $totalUnits = $args['total_units'] ?? 0;
        $avgUnitPrice = $args['avg_unit_price'] ?? 0;
        $soldUnits = $args['sold_units'] ?? 0;
        $marketingSpend = $args['marketing_spend'] ?? 0;
        $operationalCost = $args['operational_cost'] ?? 0;
        $commissionRate = $args['commission_rate'] ?? 2.5;

        $totalRevenue = $soldUnits * $avgUnitPrice;
        $totalCommission = $totalRevenue * ($commissionRate / 100);
        $totalCost = $marketingSpend + $operationalCost;
        $netProfit = $totalCommission - $totalCost;
        $roiPercent = $totalCost > 0 ? ($netProfit / $totalCost) * 100 : 0;

        return [
            'total_units' => $totalUnits,
            'sold_units' => $soldUnits,
            'sell_through_rate' => $totalUnits > 0 ? round(($soldUnits / $totalUnits) * 100, 2) : 0,
            'total_revenue' => round($totalRevenue, 2),
            'total_commission' => round($totalCommission, 2),
            'total_cost' => round($totalCost, 2),
            'net_profit' => round($netProfit, 2),
            'roi_percent' => round($roiPercent, 2),
            'roi' => round($roiPercent, 2) . '%',
            'roi_label' => 'ROI الشامل للمشروع',
        ];
    }

    private function calculatePaymentPlan(array $args): array
    {
        $price = $args['unit_price'] ?? 0;
        $downPaymentPercent = $args['down_payment_percent'] ?? 10;
        $installments = $args['installments'] ?? 12;
        $gracePeriod = $args['grace_period'] ?? false;

        $downPayment = $price * ($downPaymentPercent / 100);
        $remaining = $price - $downPayment;
        $monthlyInstallment = $installments > 0 ? $remaining / $installments : 0;

        $schedule = [];
        $startMonth = $gracePeriod ? 2 : 1;
        for ($i = 0; $i < $installments; $i++) {
            $schedule[] = [
                'month' => $startMonth + $i,
                'amount' => round($monthlyInstallment, 2),
                'remaining' => round($remaining - ($monthlyInstallment * ($i + 1)), 2),
            ];
        }

        return [
            'unit_price' => $price,
            'down_payment' => round($downPayment, 2),
            'installments' => $installments,
            'monthly_amount' => round($monthlyInstallment, 2),
            'grace_period' => $gracePeriod,
            'schedule' => $schedule,
        ];
    }
}
