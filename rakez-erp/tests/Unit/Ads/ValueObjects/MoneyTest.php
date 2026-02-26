<?php

namespace Tests\Unit\Ads\ValueObjects;

use App\Domain\Ads\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_construction_with_amount_and_currency(): void
    {
        $money = new Money(99.99, 'SAR');

        $this->assertSame(99.99, $money->amount);
        $this->assertSame('SAR', $money->currency);
    }

    public function test_default_currency_is_usd(): void
    {
        $money = new Money(50.0);

        $this->assertSame('USD', $money->currency);
    }

    public function test_zero_factory(): void
    {
        $money = Money::zero('EUR');

        $this->assertSame(0.0, $money->amount);
        $this->assertSame('EUR', $money->currency);
    }

    public function test_zero_default_currency(): void
    {
        $money = Money::zero();

        $this->assertSame('USD', $money->currency);
    }

    public function test_negate_flips_positive_to_negative(): void
    {
        $money = new Money(100.0, 'USD');
        $negated = $money->negate();

        $this->assertSame(-100.0, $negated->amount);
        $this->assertSame('USD', $negated->currency);
    }

    public function test_negate_flips_negative_to_positive(): void
    {
        $money = new Money(-50.0, 'SAR');
        $negated = $money->negate();

        $this->assertSame(50.0, $negated->amount);
        $this->assertSame('SAR', $negated->currency);
    }

    public function test_negate_preserves_zero(): void
    {
        $money = Money::zero();
        $negated = $money->negate();

        $this->assertSame(0.0, $negated->amount);
    }
}
