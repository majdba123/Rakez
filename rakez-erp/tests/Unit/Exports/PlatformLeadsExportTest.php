<?php

namespace Tests\Unit\Exports;

use App\Exports\PlatformLeadsExport;
use PHPUnit\Framework\TestCase;

class PlatformLeadsExportTest extends TestCase
{
    public function test_headings_returns_expected_columns(): void
    {
        $export = new PlatformLeadsExport([]);
        $headings = $export->headings();

        $this->assertSame([
            'platform',
            'lead_id',
            'name',
            'email',
            'phone',
            'form_id',
            'ad_id',
            'adset_id',
            'campaign_id',
            'created_time',
            'extra_data',
        ], $headings);
    }

    public function test_collection_returns_mapped_rows(): void
    {
        $rows = [
            [
                'platform' => 'meta',
                'lead_id' => 'm1',
                'name' => 'Alice',
                'email' => 'alice@test.com',
                'phone' => '+966501111111',
                'form_id' => 'f1',
                'ad_id' => 'a1',
                'adset_id' => 'as1',
                'campaign_id' => 'c1',
                'created_time' => '2026-01-15',
            ],
        ];
        $export = new PlatformLeadsExport($rows);
        $collection = $export->collection();

        $this->assertCount(1, $collection);
        $first = $collection->first();
        $this->assertIsArray($first);
        $this->assertSame('meta', $first[0]);
        $this->assertSame('m1', $first[1]);
        $this->assertSame('Alice', $first[2]);
        $this->assertSame('alice@test.com', $first[3]);
        $this->assertSame('+966501111111', $first[4]);
    }

    public function test_collection_maps_extra_data_as_json(): void
    {
        $rows = [
            [
                'platform' => 'snap',
                'lead_id' => 's1',
                'name' => 'Bob',
                'email' => '',
                'phone' => '',
                'form_id' => '',
                'ad_id' => '',
                'campaign_id' => '',
                'created_time' => '',
                'extra_data' => ['custom' => 'value', 'num' => 42],
            ],
        ];
        $export = new PlatformLeadsExport($rows);
        $collection = $export->collection();

        $first = $collection->first();
        $this->assertIsArray($first);
        $extraDataCell = $first[10];
        $this->assertNotEmpty($extraDataCell);
        $decoded = json_decode($extraDataCell, true);
        $this->assertSame('value', $decoded['custom'] ?? null);
        $this->assertSame(42, $decoded['num'] ?? null);
    }

    public function test_collection_row_count_matches_input(): void
    {
        $rows = [
            ['platform' => 'meta', 'lead_id' => '1', 'name' => 'A', 'email' => '', 'phone' => '', 'form_id' => '', 'ad_id' => '', 'campaign_id' => '', 'created_time' => ''],
            ['platform' => 'tiktok', 'lead_id' => '2', 'name' => 'B', 'email' => '', 'phone' => '', 'form_id' => '', 'ad_id' => '', 'campaign_id' => '', 'created_time' => ''],
        ];
        $export = new PlatformLeadsExport($rows);
        $this->assertCount(2, $export->collection());
    }
}
