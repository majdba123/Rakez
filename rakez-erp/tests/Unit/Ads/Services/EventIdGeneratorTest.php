<?php

namespace Tests\Unit\Ads\Services;

use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Services\EventIdGenerator;
use PHPUnit\Framework\TestCase;

class EventIdGeneratorTest extends TestCase
{
    private EventIdGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new EventIdGenerator();
    }

    public function test_deterministic_same_inputs_same_output(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');

        $id1 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time, 'order_1');
        $id2 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time, 'order_1');

        $this->assertSame($id1, $id2);
    }

    public function test_different_platform_produces_different_id(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');

        $id1 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time);
        $id2 = $this->generator->generate(Platform::Snap, 'cust_1', OutcomeType::Purchase, $time);

        $this->assertNotSame($id1, $id2);
    }

    public function test_different_customer_produces_different_id(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');

        $id1 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time);
        $id2 = $this->generator->generate(Platform::Meta, 'cust_2', OutcomeType::Purchase, $time);

        $this->assertNotSame($id1, $id2);
    }

    public function test_different_outcome_produces_different_id(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');

        $id1 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time);
        $id2 = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Refund, $time);

        $this->assertNotSame($id1, $id2);
    }

    public function test_with_order_id_differs_from_without(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');

        $withOrder = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time, 'order_1');
        $withoutOrder = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time);

        $this->assertNotSame($withOrder, $withoutOrder);
    }

    public function test_output_is_valid_sha256_hex(): void
    {
        $time = new \DateTimeImmutable('2026-01-15 10:00:00');
        $id = $this->generator->generate(Platform::Meta, 'cust_1', OutcomeType::Purchase, $time);

        $this->assertSame(64, strlen($id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
    }
}
