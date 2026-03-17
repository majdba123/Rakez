<?php

namespace App\Services\AI;

class GuardrailCheck
{
    public function __construct(
        private readonly string $metric,
        private readonly float $value,
        private readonly string $status,
        private readonly string $message,
        private readonly array $range = [],
    ) {}

    public function metric(): string
    {
        return $this->metric;
    }

    public function value(): float
    {
        return $this->value;
    }

    /**
     * @return 'ok'|'warning'|'critical'
     */
    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function range(): array
    {
        return $this->range;
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    public function isCritical(): bool
    {
        return $this->status === 'critical';
    }

    public function toArray(): array
    {
        return [
            'metric' => $this->metric,
            'value' => $this->value,
            'status' => $this->status,
            'message' => $this->message,
            'range' => $this->range,
        ];
    }
}
