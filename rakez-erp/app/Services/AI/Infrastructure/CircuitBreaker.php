<?php

namespace App\Services\AI\Infrastructure;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const PREFIX = 'ai_circuit_breaker:';

    /**
     * Check if the service is available (circuit is closed or half-open).
     */
    public function isAvailable(string $service = 'openai'): bool
    {
        $state = $this->getState($service);

        if ($state === 'closed') {
            return true;
        }

        if ($state === 'half_open') {
            $halfOpenAttempts = (int) Cache::get(self::PREFIX . $service . ':half_open_attempts', 0);
            $maxHalfOpen = (int) config('ai_assistant.circuit_breaker.half_open_max_attempts', 3);

            return $halfOpenAttempts < $maxHalfOpen;
        }

        // State is 'open' — check if timeout has elapsed
        $openedAt = (int) Cache::get(self::PREFIX . $service . ':opened_at', 0);
        $timeout = (int) config('ai_assistant.circuit_breaker.timeout_seconds', 60);

        if (time() - $openedAt >= $timeout) {
            // Transition to half-open
            $this->setState($service, 'half_open');
            Cache::put(self::PREFIX . $service . ':half_open_attempts', 0, 300);

            return true;
        }

        return false;
    }

    /**
     * Record a successful call — may close the circuit.
     */
    public function recordSuccess(string $service = 'openai'): void
    {
        $state = $this->getState($service);

        if ($state === 'half_open') {
            // Successful half-open call → close the circuit
            $this->setState($service, 'closed');
            Cache::forget(self::PREFIX . $service . ':failures');
            Cache::forget(self::PREFIX . $service . ':half_open_attempts');
        }

        if ($state === 'closed') {
            // Reset failure count on success
            Cache::forget(self::PREFIX . $service . ':failures');
        }
    }

    /**
     * Record a failed call — may open the circuit.
     */
    public function recordFailure(string $service = 'openai'): void
    {
        $state = $this->getState($service);

        if ($state === 'half_open') {
            $attempts = (int) Cache::increment(self::PREFIX . $service . ':half_open_attempts');
            $maxHalfOpen = (int) config('ai_assistant.circuit_breaker.half_open_max_attempts', 3);

            if ($attempts >= $maxHalfOpen) {
                $this->openCircuit($service);
            }

            return;
        }

        $failures = (int) Cache::increment(self::PREFIX . $service . ':failures');
        $threshold = (int) config('ai_assistant.circuit_breaker.failure_threshold', 5);

        if ($failures >= $threshold) {
            $this->openCircuit($service);
        }
    }

    /**
     * Get the current state of the circuit breaker.
     *
     * @return 'closed'|'open'|'half_open'
     */
    public function getState(string $service = 'openai'): string
    {
        return Cache::get(self::PREFIX . $service . ':state', 'closed');
    }

    /**
     * Force reset the circuit breaker to closed state.
     */
    public function reset(string $service = 'openai'): void
    {
        $this->setState($service, 'closed');
        Cache::forget(self::PREFIX . $service . ':failures');
        Cache::forget(self::PREFIX . $service . ':opened_at');
        Cache::forget(self::PREFIX . $service . ':half_open_attempts');
    }

    private function openCircuit(string $service): void
    {
        $this->setState($service, 'open');
        Cache::put(self::PREFIX . $service . ':opened_at', time(), 600);
    }

    private function setState(string $service, string $state): void
    {
        Cache::put(self::PREFIX . $service . ':state', $state, 600);
    }
}
