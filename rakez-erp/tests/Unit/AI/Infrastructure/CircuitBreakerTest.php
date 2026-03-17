<?php

namespace Tests\Unit\AI\Infrastructure;

use App\Services\AI\Infrastructure\CircuitBreaker;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = new CircuitBreaker;
        $this->breaker->reset('test_service');
    }

    public function test_initially_closed_and_available(): void
    {
        $this->assertEquals('closed', $this->breaker->getState('test_service'));
        $this->assertTrue($this->breaker->isAvailable('test_service'));
    }

    public function test_stays_closed_under_threshold(): void
    {
        // Default threshold is 5
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure('test_service');
        }

        $this->assertEquals('closed', $this->breaker->getState('test_service'));
        $this->assertTrue($this->breaker->isAvailable('test_service'));
    }

    public function test_opens_after_threshold_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('test_service');
        }

        $this->assertEquals('open', $this->breaker->getState('test_service'));
        $this->assertFalse($this->breaker->isAvailable('test_service'));
    }

    public function test_success_resets_failure_count(): void
    {
        $this->breaker->recordFailure('test_service');
        $this->breaker->recordFailure('test_service');
        $this->breaker->recordSuccess('test_service');

        // Now add 4 more failures — should not open because count was reset
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure('test_service');
        }

        $this->assertEquals('closed', $this->breaker->getState('test_service'));
    }

    public function test_reset_restores_to_closed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('test_service');
        }

        $this->assertEquals('open', $this->breaker->getState('test_service'));

        $this->breaker->reset('test_service');

        $this->assertEquals('closed', $this->breaker->getState('test_service'));
        $this->assertTrue($this->breaker->isAvailable('test_service'));
    }

    public function test_independent_services(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure('service_a');
        }

        $this->assertFalse($this->breaker->isAvailable('service_a'));
        $this->assertTrue($this->breaker->isAvailable('service_b'));

        $this->breaker->reset('service_a');
        $this->breaker->reset('service_b');
    }
}
