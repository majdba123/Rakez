<?php

namespace Tests\Unit\Ads;

use App\Services\Ads\SnapCsvLeadNormalizer;
use PHPUnit\Framework\TestCase;

class SnapCsvLeadNormalizerTest extends TestCase
{
    private SnapCsvLeadNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new SnapCsvLeadNormalizer;
    }

    public function test_normalizes_csv_with_standard_headers(): void
    {
        $csv = "name,email,phone,created_time\nJohn Doe,john@test.com,+966501234567,2026-01-15 10:00";
        $result = $this->normalizer->normalizeCsv($csv);

        $this->assertCount(1, $result);
        $this->assertSame('snap', $result[0]['platform']);
        $this->assertSame('John Doe', $result[0]['name']);
        $this->assertSame('john@test.com', $result[0]['email']);
        $this->assertSame('+966501234567', $result[0]['phone']);
        $this->assertSame('2026-01-15 10:00', $result[0]['created_time']);
        $this->assertArrayHasKey('lead_id', $result[0]);
        $this->assertSame('1', $result[0]['lead_id']);
    }

    public function test_maps_alternative_headers_full_name_phone_number(): void
    {
        $csv = "full_name,phone_number,email address\nJane Doe,+966509876543,jane@test.com";
        $result = $this->normalizer->normalizeCsv($csv);

        $this->assertCount(1, $result);
        $this->assertSame('snap', $result[0]['platform']);
        $this->assertSame('Jane Doe', $result[0]['name']);
        $this->assertSame('+966509876543', $result[0]['phone']);
        $this->assertSame('jane@test.com', $result[0]['email']);
    }

    public function test_empty_csv_returns_empty_array(): void
    {
        $this->assertSame([], $this->normalizer->normalizeCsv(''));
    }

    public function test_header_only_returns_empty_array(): void
    {
        $csv = "name,email,phone";
        $result = $this->normalizer->normalizeCsv($csv);
        $this->assertSame([], $result);
    }

    public function test_extra_columns_go_to_extra_data(): void
    {
        $csv = "name,email,phone,custom_field,another\nTest,test@x.com,123,value1,value2";
        $result = $this->normalizer->normalizeCsv($csv);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('extra_data', $result[0]);
        $this->assertSame('value1', $result[0]['extra_data']['custom_field'] ?? null);
        $this->assertSame('value2', $result[0]['extra_data']['another'] ?? null);
    }

    public function test_multiple_rows_normalized(): void
    {
        $csv = "name,email\nA,a@x.com\nB,b@x.com";
        $result = $this->normalizer->normalizeCsv($csv);
        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['name']);
        $this->assertSame('B', $result[1]['name']);
        $this->assertSame('1', $result[0]['lead_id']);
        $this->assertSame('2', $result[1]['lead_id']);
    }
}
