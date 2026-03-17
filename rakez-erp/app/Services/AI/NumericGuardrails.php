<?php

namespace App\Services\AI;

class NumericGuardrails
{
    private array $config;

    public function __construct()
    {
        $this->config = config('ai_guardrails', []);
    }

    /**
     * Validate an ROI value against configured ranges.
     *
     * @param  string  $type  'romi' | 'project_roi'
     */
    public function validateROI(float $value, string $type = 'romi'): GuardrailCheck
    {
        $range = $this->config[$type] ?? $this->config['romi'] ?? ['min' => 100, 'max' => 2000];
        $label = $range['label'] ?? strtoupper($type);
        $unit = $range['unit'] ?? '%';
        $min = $range['min'] ?? 0;
        $max = $range['max'] ?? PHP_FLOAT_MAX;

        if ($value < $min) {
            return new GuardrailCheck(
                metric: $type,
                value: $value,
                status: 'critical',
                message: "{$label}: القيمة {$value}{$unit} أقل من الحد الأدنى المرجعي ({$min}{$unit}). تحقق من البيانات.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        if ($value > $max) {
            return new GuardrailCheck(
                metric: $type,
                value: $value,
                status: 'warning',
                message: "{$label}: القيمة {$value}{$unit} أعلى من النطاق المرجعي ({$max}{$unit}). قد تكون البيانات غير دقيقة.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        return new GuardrailCheck(
            metric: $type,
            value: $value,
            status: 'ok',
            message: "{$label}: {$value}{$unit} ضمن النطاق الطبيعي.",
            range: ['min' => $min, 'max' => $max],
        );
    }

    /**
     * Validate a Cost Per Lead value.
     */
    public function validateCPL(float $value, ?string $platform = null, ?string $region = null): GuardrailCheck
    {
        $cplConfig = $this->config['cpl'] ?? ['min' => 15, 'max' => 150];
        $unit = $cplConfig['unit'] ?? 'SAR';

        // Use channel-specific range if platform is provided
        $min = $cplConfig['min'];
        $max = $cplConfig['max'];

        if ($platform && isset($cplConfig['channels'][$platform])) {
            $channelRange = $cplConfig['channels'][$platform];
            $min = $channelRange['min'];
            $max = $channelRange['max'];
        }

        // Apply regional multiplier if region is provided
        if ($region && isset($this->config['regional_benchmarks'][$region])) {
            $multiplier = $this->config['regional_benchmarks'][$region]['cpl_multiplier'] ?? 1.0;
            $min = round($min * $multiplier, 2);
            $max = round($max * $multiplier, 2);
        }

        $label = $cplConfig['label'] ?? 'تكلفة الليد';
        $context = $platform ? " ({$platform})" : '';
        $context .= $region ? " — {$region}" : '';

        if ($value < $min * 0.5) {
            return new GuardrailCheck(
                metric: 'cpl',
                value: $value,
                status: 'warning',
                message: "{$label}{$context}: {$value} {$unit} منخفضة جداً — تأكد من جودة الليدات.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        if ($value > $max) {
            return new GuardrailCheck(
                metric: 'cpl',
                value: $value,
                status: 'critical',
                message: "{$label}{$context}: {$value} {$unit} أعلى من الحد المرجعي ({$max} {$unit}). راجع استهداف الحملة.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        return new GuardrailCheck(
            metric: 'cpl',
            value: $value,
            status: 'ok',
            message: "{$label}{$context}: {$value} {$unit} ضمن النطاق الطبيعي.",
            range: ['min' => $min, 'max' => $max],
        );
    }

    /**
     * Validate a close rate value.
     */
    public function validateCloseRate(float $value): GuardrailCheck
    {
        $range = $this->config['close_rate'] ?? ['min' => 5, 'max' => 15];
        $min = $range['min'];
        $max = $range['max'];
        $label = $range['label'] ?? 'نسبة الإغلاق';

        if ($value < $min) {
            return new GuardrailCheck(
                metric: 'close_rate',
                value: $value,
                status: 'critical',
                message: "{$label}: {$value}% أقل من الحد الأدنى ({$min}%). تحتاج تحسين عملية المبيعات.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        if ($value > $max) {
            return new GuardrailCheck(
                metric: 'close_rate',
                value: $value,
                status: 'warning',
                message: "{$label}: {$value}% أعلى من المتوسط ({$max}%). تحقق من دقة البيانات.",
                range: ['min' => $min, 'max' => $max],
            );
        }

        return new GuardrailCheck(
            metric: 'close_rate',
            value: $value,
            status: 'ok',
            message: "{$label}: {$value}% ضمن النطاق الطبيعي.",
            range: ['min' => $min, 'max' => $max],
        );
    }

    /**
     * Validate DTI (Debt-to-Income) ratio for mortgage calculations.
     */
    public function validateDTI(float $dtiPercent): GuardrailCheck
    {
        $maxDti = $this->config['mortgage']['max_dti'] ?? 55;

        if ($dtiPercent > $maxDti) {
            return new GuardrailCheck(
                metric: 'dti',
                value: $dtiPercent,
                status: 'critical',
                message: "نسبة الاستقطاع {$dtiPercent}% تتجاوز حد ساما ({$maxDti}%). التمويل غير ممكن.",
                range: ['max' => $maxDti],
            );
        }

        if ($dtiPercent > $maxDti * 0.9) {
            return new GuardrailCheck(
                metric: 'dti',
                value: $dtiPercent,
                status: 'warning',
                message: "نسبة الاستقطاع {$dtiPercent}% قريبة من الحد الأقصى ({$maxDti}%).",
                range: ['max' => $maxDti],
            );
        }

        return new GuardrailCheck(
            metric: 'dti',
            value: $dtiPercent,
            status: 'ok',
            message: "نسبة الاستقطاع {$dtiPercent}% ضمن الحد المسموح.",
            range: ['max' => $maxDti],
        );
    }
}
