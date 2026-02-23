<?php

namespace App\Services\AI;

class NumericGuardrails
{
    /** Validate a CPL value against Saudi market ranges. */
    public function validateCPL(float $value, string $channel = 'mixed', string $region = 'الرياض'): GuardrailResult
    {
        $cfg = config('ai_guardrails.cpl', ['min' => 15, 'max' => 150]);
        $channelOverrides = $cfg['channels'] ?? [];

        $min = $cfg['min'];
        $max = $cfg['max'];

        if (isset($channelOverrides[$channel])) {
            $min = $channelOverrides[$channel]['min'];
            $max = $channelOverrides[$channel]['max'];
        }

        return new GuardrailResult(
            'cpl',
            $value,
            $min,
            $max,
            ["القناة: {$channel}", "المنطقة: {$region}", "النطاق المرجعي: {$min}–{$max} ريال"]
        );
    }

    /** Validate a close rate percentage. */
    public function validateCloseRate(float $value): GuardrailResult
    {
        $cfg = config('ai_guardrails.close_rate', ['min' => 5, 'max' => 15]);

        return new GuardrailResult(
            'close_rate',
            $value,
            $cfg['min'],
            $cfg['max'],
            ['نسبة الإغلاق الجيدة بالعقار السعودي: 5–15%']
        );
    }

    /**
     * Validate an ROI value, distinguishing ROMI from Project ROI.
     * @param string $type 'romi' | 'project_roi'
     */
    public function validateROI(float $value, string $type = 'romi'): GuardrailResult
    {
        $key = $type === 'project_roi' ? 'project_roi' : 'romi';
        $cfg = config("ai_guardrails.{$key}", ['min' => 10, 'max' => 2000]);
        $label = $type === 'project_roi'
            ? 'هذا ROI الشامل للمشروع (يشمل تكاليف الأرض والبناء)'
            : 'هذا ROMI (عائد التسويق فقط — بدون تكاليف الأرض والبناء)';

        return new GuardrailResult(
            $key,
            $value,
            $cfg['min'],
            $cfg['max'],
            [$label]
        );
    }

    /**
     * Standard mortgage payment using the annuity formula.
     * PMT = PV × [r(1+r)^n] / [(1+r)^n − 1]
     */
    public function calculateMortgage(float $pv, float $annualRate, int $years): array
    {
        $monthlyRate = ($annualRate / 100) / 12;
        $totalMonths = $years * 12;

        if ($monthlyRate <= 0 || $totalMonths <= 0 || $pv <= 0) {
            return [
                'monthly_payment' => 0.0,
                'total_payment' => 0.0,
                'total_interest' => 0.0,
                'max_dti' => (float) config('ai_guardrails.mortgage.max_dti', 55),
                'min_salary_required' => 0.0,
            ];
        }

        $factor = pow(1 + $monthlyRate, $totalMonths);
        $monthlyPayment = $pv * ($monthlyRate * $factor) / ($factor - 1);
        $totalPayment = $monthlyPayment * $totalMonths;
        $totalInterest = $totalPayment - $pv;
        $maxDti = (float) config('ai_guardrails.mortgage.max_dti', 55);
        $minSalary = $monthlyPayment / ($maxDti / 100);

        return [
            'monthly_payment' => round($monthlyPayment, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2),
            'max_dti' => $maxDti,
            'min_salary_required' => round($minSalary, 2),
        ];
    }

    /** Generic range validation for any metric. */
    public function validateRange(string $metric, float $value, float $min, float $max): GuardrailResult
    {
        return new GuardrailResult($metric, $value, $min, $max);
    }
}
