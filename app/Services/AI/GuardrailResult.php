<?php

namespace App\Services\AI;

class GuardrailResult
{
    public bool $inRange;

    public ?string $justification;

    public array $assumptions;

    public float $value;

    public float $min;

    public float $max;

    public string $metric;

    public function __construct(
        string $metric,
        float $value,
        float $min,
        float $max,
        array $assumptions = [],
        ?string $justification = null
    ) {
        $this->metric = $metric;
        $this->value = $value;
        $this->min = $min;
        $this->max = $max;
        $this->inRange = $value >= $min && $value <= $max;
        $this->assumptions = $assumptions;
        $this->justification = $justification ?? ($this->inRange
            ? null
            : "القيمة {$value} خارج النطاق المرجعي ({$min} – {$max})");
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'value' => $this->value,
            'min' => $this->min,
            'max' => $this->max,
            'in_range' => $this->inRange,
            'justification' => $this->justification,
            'assumptions' => $this->assumptions,
        ];
    }
}
