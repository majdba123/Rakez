<?php

namespace Tests\Unit\Ads\ValueObjects;

use App\Domain\Ads\ValueObjects\Platform;
use PHPUnit\Framework\TestCase;

class PlatformTest extends TestCase
{
    public function test_from_meta(): void
    {
        $this->assertSame(Platform::Meta, Platform::from('meta'));
    }

    public function test_from_snap(): void
    {
        $this->assertSame(Platform::Snap, Platform::from('snap'));
    }

    public function test_from_tiktok(): void
    {
        $this->assertSame(Platform::TikTok, Platform::from('tiktok'));
    }

    public function test_cases_returns_three_platforms(): void
    {
        $this->assertCount(3, Platform::cases());
    }

    public function test_value_matches_string(): void
    {
        $this->assertSame('meta', Platform::Meta->value);
        $this->assertSame('snap', Platform::Snap->value);
        $this->assertSame('tiktok', Platform::TikTok->value);
    }

    public function test_from_invalid_value_throws(): void
    {
        $this->expectException(\ValueError::class);
        Platform::from('google');
    }
}
