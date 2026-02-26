<?php

namespace Tests\Unit\Ads\ValueObjects;

use App\Domain\Ads\ValueObjects\OutcomeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OutcomeTypeTest extends TestCase
{
    public function test_all_nine_cases_exist(): void
    {
        $this->assertCount(9, OutcomeType::cases());
    }

    #[DataProvider('allCasesProvider')]
    public function test_from_string(string $value, OutcomeType $expected): void
    {
        $this->assertSame($expected, OutcomeType::from($value));
    }

    public static function allCasesProvider(): array
    {
        return [
            ['LEAD_QUALIFIED', OutcomeType::LeadQualified],
            ['LEAD_DISQUALIFIED', OutcomeType::LeadDisqualified],
            ['DEAL_WON', OutcomeType::DealWon],
            ['DEAL_LOST', OutcomeType::DealLost],
            ['PURCHASE', OutcomeType::Purchase],
            ['REFUND', OutcomeType::Refund],
            ['RETENTION_D7', OutcomeType::RetentionD7],
            ['RETENTION_D30', OutcomeType::RetentionD30],
            ['LTV_UPDATE', OutcomeType::LtvUpdate],
        ];
    }

    #[DataProvider('positiveProvider')]
    public function test_is_positive_returns_true_for_good_signals(OutcomeType $type): void
    {
        $this->assertTrue($type->isPositive());
    }

    public static function positiveProvider(): array
    {
        return [
            [OutcomeType::LeadQualified],
            [OutcomeType::DealWon],
            [OutcomeType::Purchase],
            [OutcomeType::RetentionD7],
            [OutcomeType::RetentionD30],
            [OutcomeType::LtvUpdate],
        ];
    }

    #[DataProvider('negativeProvider')]
    public function test_is_positive_returns_false_for_bad_signals(OutcomeType $type): void
    {
        $this->assertFalse($type->isPositive());
    }

    public static function negativeProvider(): array
    {
        return [
            [OutcomeType::LeadDisqualified],
            [OutcomeType::DealLost],
            [OutcomeType::Refund],
        ];
    }

    public function test_from_invalid_value_throws(): void
    {
        $this->expectException(\ValueError::class);
        OutcomeType::from('INVALID');
    }
}
