<?php

namespace Tests\Unit\Services;

use App\Services\Accounting\ProjectCommissionCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectCommissionCalculatorTest extends TestCase
{
    private ProjectCommissionCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new ProjectCommissionCalculator;
    }

    public function test_buyer_formula_is_unit_times_rate_over_100(): void
    {
        $result = $this->calculator->calculateBuyer(100000, 2);

        $this->assertSame('buyer', $result['source']);
        $this->assertSame(100000.0, $result['base_amount']);
        $this->assertSame(2.0, $result['commission_percentage']);
        $this->assertSame(ProjectCommissionCalculator::BUYER_FORMULA_KEY, $result['formula_key']);
        $this->assertSame(2000.0, $result['commission_amount']);
    }

    public function test_owner_formula_divides_by_one_plus_rate_over_100(): void
    {
        $result = $this->calculator->calculateOwner(100000, 2);

        $expected = round(100000 / 1.02, 2);
        $this->assertSame('owner', $result['source']);
        $this->assertSame(ProjectCommissionCalculator::OWNER_FORMULA_KEY, $result['formula_key']);
        $this->assertSame($expected, $result['commission_amount']);
    }

    public function test_owner_formula_does_not_subtract_from_price(): void
    {
        $result = $this->calculator->calculateOwner(100000, 2);
        $incorrectSubtractionStyle = round(100000 - (100000 / 1.02), 2);

        $this->assertNotSame($incorrectSubtractionStyle, $result['commission_amount']);
    }

    public function test_unsupported_source_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate('tenant', 1000, 1);
    }

    public function test_calculate_routes_buyer_and_owner_correct_formula_keys(): void
    {
        $buy = $this->calculator->calculate('buyer', 50_000, 4);
        $owner = $this->calculator->calculate('OWNER', 50_000, 4); // casing normalized

        $this->assertSame(ProjectCommissionCalculator::BUYER_FORMULA_KEY, $buy['formula_key']);
        $this->assertSame(ProjectCommissionCalculator::OWNER_FORMULA_KEY, $owner['formula_key']);
    }
}
