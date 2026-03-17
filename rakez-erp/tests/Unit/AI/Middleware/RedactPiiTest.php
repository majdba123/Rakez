<?php

namespace Tests\Unit\AI\Middleware;

use App\Http\Middleware\RedactPiiFromAi;
use PHPUnit\Framework\TestCase;

class RedactPiiTest extends TestCase
{
    private RedactPiiFromAi $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RedactPiiFromAi;
    }

    public function test_redacts_saudi_national_id(): void
    {
        $text = 'رقم الهوية 1098765432 للعميل';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('1098765432', $result);
        $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $result);
    }

    public function test_redacts_saudi_national_id_starting_with_2(): void
    {
        $text = 'إقامة رقم 2098765432';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('2098765432', $result);
        $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $result);
    }

    public function test_redacts_saudi_mobile_number(): void
    {
        $text = 'الجوال 0512345678';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('0512345678', $result);
        $this->assertStringContainsString('[REDACTED_PHONE]', $result);
    }

    public function test_redacts_international_saudi_number(): void
    {
        $text = 'رقم الهاتف +966512345678';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('+966512345678', $result);
        $this->assertStringContainsString('[REDACTED_PHONE]', $result);
    }

    public function test_redacts_saudi_iban(): void
    {
        $text = 'الآيبان SA0380000000608010167519';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('SA0380000000608010167519', $result);
        $this->assertStringContainsString('[REDACTED_IBAN]', $result);
    }

    public function test_redacts_email(): void
    {
        $text = 'الإيميل test@example.com';
        $result = $this->middleware->redact($text);

        $this->assertStringNotContainsString('test@example.com', $result);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
    }

    public function test_preserves_normal_text(): void
    {
        $text = 'كم عدد الليدات هذا الشهر؟';
        $result = $this->middleware->redact($text);

        $this->assertEquals($text, $result);
    }

    public function test_redacts_multiple_pii_in_same_text(): void
    {
        $text = 'العميل هوية 1098765432 جوال 0512345678 إيميل test@test.com';
        $result = $this->middleware->redact($text);

        $this->assertStringContainsString('[REDACTED_NATIONAL_ID]', $result);
        $this->assertStringContainsString('[REDACTED_PHONE]', $result);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $result);
    }
}
