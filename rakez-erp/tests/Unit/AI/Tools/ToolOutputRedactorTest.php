<?php

namespace Tests\Unit\AI\Tools;

use App\Services\AI\Tools\ToolOutputRedactor;
use PHPUnit\Framework\TestCase;

class ToolOutputRedactorTest extends TestCase
{
    private ToolOutputRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new ToolOutputRedactor;
    }

    // ── Key-based redaction ──

    public function test_redacts_phone_key_in_json_payload(): void
    {
        $input = json_encode(['name' => 'أحمد', 'phone' => '0512345678']);
        $result = $this->redactor->redact($input);
        $decoded = json_decode($result, true);

        $this->assertSame('[REDACTED_PHONE]', $decoded['phone']);
        $this->assertSame('أحمد', $decoded['name']);
    }

    public function test_redacts_mobile_key(): void
    {
        $input = json_encode(['mobile' => '0598765432']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_MOBILE]', $decoded['mobile']);
    }

    public function test_redacts_email_key(): void
    {
        $input = json_encode(['email' => 'user@example.com']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_EMAIL]', $decoded['email']);
    }

    public function test_redacts_contact_info_key(): void
    {
        $input = json_encode(['contact_info' => '0512345678 user@test.com']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_CONTACT_INFO]', $decoded['contact_info']);
    }

    public function test_redacts_whatsapp_key(): void
    {
        $input = json_encode(['whatsapp' => '+966512345678']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_WHATSAPP]', $decoded['whatsapp']);
    }

    public function test_redacts_client_mobile_key(): void
    {
        $input = json_encode(['client_mobile' => '0555555555']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_CLIENT_MOBILE]', $decoded['client_mobile']);
    }

    public function test_redacts_client_email_key(): void
    {
        $input = json_encode(['client_email' => 'client@co.sa']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_CLIENT_EMAIL]', $decoded['client_email']);
    }

    // ── Nested array redaction ──

    public function test_redacts_phone_in_nested_array(): void
    {
        $input = json_encode([
            'customers' => [
                ['name' => 'عميل 1', 'phone' => '0512345678'],
                ['name' => 'عميل 2', 'phone' => '0598765432'],
            ],
        ]);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_PHONE]', $decoded['customers'][0]['phone']);
        $this->assertSame('[REDACTED_PHONE]', $decoded['customers'][1]['phone']);
        $this->assertSame('عميل 1', $decoded['customers'][0]['name']);
    }

    // ── Regex-based redaction in string values ──

    public function test_redacts_saudi_mobile_in_string_values(): void
    {
        $input = json_encode(['notes' => 'اتصل على 0512345678 للمتابعة']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertStringNotContainsString('0512345678', $decoded['notes']);
        $this->assertStringContainsString('[REDACTED_PHONE]', $decoded['notes']);
    }

    public function test_redacts_international_phone_in_string_values(): void
    {
        $input = json_encode(['notes' => 'الرقم +966512345678']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertStringNotContainsString('+966512345678', $decoded['notes']);
    }

    public function test_redacts_email_in_string_values(): void
    {
        $input = json_encode(['notes' => 'ايميله test@example.com']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertStringNotContainsString('test@example.com', $decoded['notes']);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $decoded['notes']);
    }

    // ── Non-JSON / edge cases ──

    public function test_fallback_regex_on_non_json_string(): void
    {
        $input = 'العميل جواله 0512345678 وايميله a@b.com';
        $result = $this->redactor->redact($input);

        $this->assertStringNotContainsString('0512345678', $result);
        $this->assertStringNotContainsString('a@b.com', $result);
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertSame('', $this->redactor->redact(''));
    }

    public function test_preserves_non_sensitive_data(): void
    {
        $input = json_encode(['id' => 42, 'name' => 'مشروع أ', 'status' => 'active']);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame(42, $decoded['id']);
        $this->assertSame('مشروع أ', $decoded['name']);
        $this->assertSame('active', $decoded['status']);
    }

    public function test_handles_null_values_in_json(): void
    {
        $input = json_encode(['phone' => null, 'name' => 'test']);
        $decoded = json_decode($this->redactor->redact($input), true);

        // null phone should not be redacted (only non-empty strings)
        $this->assertNull($decoded['phone']);
    }

    public function test_handles_numeric_values(): void
    {
        // PHP treats 0512345678 as octal; use integer directly
        $input = json_encode(['phone' => 512345678, 'count' => 5]);
        $decoded = json_decode($this->redactor->redact($input), true);

        // Numeric values (not strings) should pass through
        $this->assertSame(5, $decoded['count']);
    }

    public function test_redaction_is_deterministic(): void
    {
        $input = json_encode(['phone' => '0512345678', 'email' => 'x@y.com']);
        $a = $this->redactor->redact($input);
        $b = $this->redactor->redact($input);

        $this->assertSame($a, $b);
    }

    public function test_searchCustomers_phone_does_not_leak(): void
    {
        // Simulate SearchRecordsTool output for customers module
        $input = json_encode([
            'customers' => [
                ['id' => 1, 'label' => 'أحمد', 'phone' => '0512345678'],
            ],
        ]);
        $decoded = json_decode($this->redactor->redact($input), true);

        $this->assertSame('[REDACTED_PHONE]', $decoded['customers'][0]['phone']);
        $this->assertSame('أحمد', $decoded['customers'][0]['label']);
    }
}
