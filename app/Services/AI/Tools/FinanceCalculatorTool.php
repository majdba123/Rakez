<?php

namespace App\Services\AI\Tools;

use App\Models\User;
use App\Services\AI\NumericGuardrails;

class FinanceCalculatorTool implements ToolContract
{
    public function __construct(
        private readonly NumericGuardrails $guardrails
    ) {}

    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('credit.financing.manage') && ! $user->can('sales.payment-plan.manage')
            && ! $user->can('accounting.dashboard.view') && ! $user->can('sales.dashboard.view')) {
            return ToolResponse::denied('credit.financing.manage');
        }

        $calcType = $args['calculation_type'] ?? 'mortgage';

        return match ($calcType) {
            'mortgage' => $this->calculateMortgage($args),
            'commission' => $this->calculateCommission($args),
            'roi', 'romi' => $this->calculateROMI($args),
            'project_roi' => $this->calculateProjectROI($args),
            'payment_plan' => $this->calculatePaymentPlan($args),
            default => [
                'result' => ['error' => "نوع الحساب غير معروف: {$calcType}"],
                'source_refs' => [],
            ],
        };
    }

    private function calculateMortgage(array $args): array
    {
        $unitPrice = (float) ($args['unit_price'] ?? 0);
        $downPaymentPercent = (float) ($args['down_payment_percent'] ?? 10);
        $annualRate = (float) ($args['annual_rate'] ?? 5.5);
        $years = (int) ($args['years'] ?? 25);

        $inputs = compact('unitPrice', 'downPaymentPercent', 'annualRate', 'years');

        $downPayment = $unitPrice * ($downPaymentPercent / 100);
        $loanAmount = $unitPrice - $downPayment;

        $mortgage = $this->guardrails->calculateMortgage($loanAmount, $annualRate, $years);

        $assumptions = [
            'الدفعة المقدمة ' . $downPaymentPercent . '% من قيمة الوحدة',
            'القسط الشهري ما يتجاوز 55% من الراتب (حسب ساما)',
            'الحد الأدنى للراتب المطلوب: ' . number_format($mortgage['min_salary_required']) . ' ريال',
            'الحسبة تقريبية — البنك يحدد النسبة النهائية حسب الملف الائتماني',
        ];

        return ToolResponse::success('tool_finance_calculator', $inputs, [
            'calculation_type' => 'mortgage',
            'unit_price' => $unitPrice,
            'down_payment' => round($downPayment),
            'loan_amount' => round($loanAmount),
            'monthly_payment' => round($mortgage['monthly_payment']),
            'total_payment' => round($mortgage['total_payment']),
            'total_interest' => round($mortgage['total_interest']),
            'annual_rate' => $annualRate,
            'years' => $years,
            'min_salary_required' => round($mortgage['min_salary_required']),
        ], [['type' => 'tool', 'title' => 'Finance Calculator', 'ref' => 'tool_finance_calculator']], $assumptions);
    }

    private function calculateCommission(array $args): array
    {
        $salePrice = (float) ($args['sale_price'] ?? 0);
        $commissionRate = (float) ($args['commission_rate'] ?? 2.5);
        $agentCount = max(1, (int) ($args['agent_count'] ?? 1));
        $leaderShare = (float) ($args['leader_share_percent'] ?? 10);

        $inputs = compact('salePrice', 'commissionRate', 'agentCount', 'leaderShare');

        $totalCommission = $salePrice * ($commissionRate / 100);
        $leaderAmount = $totalCommission * ($leaderShare / 100);
        $agentsPool = $totalCommission - $leaderAmount;
        $perAgent = $agentCount > 0 ? $agentsPool / $agentCount : 0;

        return ToolResponse::success('tool_finance_calculator', $inputs, [
            'calculation_type' => 'commission',
            'sale_price' => $salePrice,
            'commission_rate' => $commissionRate,
            'total_commission' => round($totalCommission),
            'leader_share' => round($leaderAmount),
            'agents_pool' => round($agentsPool),
            'per_agent' => round($perAgent),
            'agent_count' => $agentCount,
        ], [['type' => 'tool', 'title' => 'Commission Calculator', 'ref' => 'tool_finance_calculator']], [
            "نسبة العمولة: {$commissionRate}%",
            "حصة القائد: {$leaderShare}% من إجمالي العمولة",
        ]);
    }

    /**
     * ROMI — Marketing ROI: revenue from sales attributed to marketing spend ONLY.
     */
    private function calculateROMI(array $args): array
    {
        $soldUnits = (int) ($args['sold_units'] ?? 0);
        $avgPrice = (float) ($args['avg_unit_price'] ?? 1000000);
        $marketingSpend = (float) ($args['marketing_spend'] ?? 0);

        $inputs = compact('soldUnits', 'avgPrice', 'marketingSpend');

        $totalRevenue = $soldUnits * $avgPrice;
        $romi = $marketingSpend > 0 ? round((($totalRevenue - $marketingSpend) / $marketingSpend) * 100, 1) : 0;

        $assumptions = [
            'هذا ROMI (عائد التسويق فقط) — لا يشمل تكاليف الأرض والبناء',
            'الإيرادات = عدد الوحدات المباعة × متوسط سعر الوحدة',
            'ROI الشامل للمشروع يحتاج تكاليف إضافية (استخدم project_roi)',
        ];

        $response = ToolResponse::success('tool_finance_calculator', $inputs, [
            'calculation_type' => 'romi',
            'label' => 'ROMI — عائد الاستثمار التسويقي',
            'sold_units' => $soldUnits,
            'total_revenue' => round($totalRevenue),
            'marketing_spend' => round($marketingSpend),
            'romi_percent' => $romi,
        ], [['type' => 'tool', 'title' => 'ROMI Calculator', 'ref' => 'tool_finance_calculator']], $assumptions);

        return ToolResponse::withGuardrails($response, $this->guardrails->validateROI($romi, 'romi'));
    }

    /**
     * Project ROI — total project return including land, construction, and operational costs.
     */
    private function calculateProjectROI(array $args): array
    {
        $totalUnits = max(1, (int) ($args['total_units'] ?? 100));
        $avgPrice = (float) ($args['avg_unit_price'] ?? 1000000);
        $soldUnits = (int) ($args['sold_units'] ?? 0);
        $marketingSpend = (float) ($args['marketing_spend'] ?? 0);
        $operationalCost = (float) ($args['operational_cost'] ?? 0);

        $inputs = compact('totalUnits', 'avgPrice', 'soldUnits', 'marketingSpend', 'operationalCost');

        $totalRevenue = $soldUnits * $avgPrice;
        $totalCost = $marketingSpend + $operationalCost;
        $profit = $totalRevenue - $totalCost;
        $roi = $totalCost > 0 ? round(($profit / $totalCost) * 100, 1) : 0;
        $sellRate = round(($soldUnits / $totalUnits) * 100, 1);
        $costPerUnit = $soldUnits > 0 ? round($totalCost / $soldUnits) : 0;
        $remainingUnits = $totalUnits - $soldUnits;
        $remainingRevenue = $remainingUnits * $avgPrice;

        $assumptions = [
            'هذا ROI الشامل للمشروع (يشمل كل التكاليف التشغيلية)',
            'لا يشمل تكلفة الأرض والبناء — أضفها بـ operational_cost لنتيجة أدق',
        ];

        $response = ToolResponse::success('tool_finance_calculator', $inputs, [
            'calculation_type' => 'project_roi',
            'label' => 'ROI الشامل للمشروع',
            'total_units' => $totalUnits,
            'sold_units' => $soldUnits,
            'remaining_units' => $remainingUnits,
            'sell_rate' => $sellRate . '%',
            'total_revenue' => round($totalRevenue),
            'remaining_potential_revenue' => round($remainingRevenue),
            'total_cost' => round($totalCost),
            'profit' => round($profit),
            'roi' => $roi . '%',
            'cost_per_sold_unit' => $costPerUnit,
            'marketing_cost_per_unit' => $soldUnits > 0 ? round($marketingSpend / $soldUnits) : 0,
        ], [['type' => 'tool', 'title' => 'Project ROI Calculator', 'ref' => 'tool_finance_calculator']], $assumptions);

        return ToolResponse::withGuardrails($response, $this->guardrails->validateROI($roi, 'project_roi'));
    }

    private function calculatePaymentPlan(array $args): array
    {
        $unitPrice = (float) ($args['unit_price'] ?? 0);
        $downPaymentPercent = (float) ($args['down_payment_percent'] ?? 10);
        $installments = max(1, (int) ($args['installments'] ?? 12));
        $hasGracePeriod = (bool) ($args['grace_period'] ?? false);

        $inputs = compact('unitPrice', 'downPaymentPercent', 'installments', 'hasGracePeriod');

        $downPayment = $unitPrice * ($downPaymentPercent / 100);
        $remaining = $unitPrice - $downPayment;
        $monthlyInstallment = round($remaining / $installments);

        $schedule = [];
        $startMonth = $hasGracePeriod ? 4 : 1;
        for ($i = 0; $i < min($installments, 6); $i++) {
            $schedule[] = [
                'month' => $startMonth + $i,
                'amount' => $monthlyInstallment,
                'remaining' => round($remaining - ($monthlyInstallment * ($i + 1))),
            ];
        }
        if ($installments > 6) {
            $schedule[] = ['note' => '... و' . ($installments - 6) . ' أقساط إضافية بنفس المبلغ'];
        }

        return ToolResponse::success('tool_finance_calculator', $inputs, [
            'calculation_type' => 'payment_plan',
            'unit_price' => $unitPrice,
            'down_payment' => round($downPayment),
            'remaining' => round($remaining),
            'installments' => $installments,
            'monthly_installment' => $monthlyInstallment,
            'grace_period' => $hasGracePeriod ? '3 شهور' : 'لا يوجد',
            'sample_schedule' => $schedule,
        ], [['type' => 'tool', 'title' => 'Payment Plan Calculator', 'ref' => 'tool_finance_calculator']], [
            'خطة دفع مباشرة بدون فوائد بنكية',
            'الأقساط متساوية',
        ]);
    }
}
