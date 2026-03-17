<?php

namespace Tests\Unit\Ads;

use App\Models\Lead;
use App\Services\Ads\PlatformLeadSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformLeadSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlatformLeadSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlatformLeadSyncService;
    }

    public function test_sync_creates_leads_with_platform_lead_id_and_campaign_platform(): void
    {
        $rows = [
            [
                'platform' => 'meta',
                'lead_id' => 'meta_lead_1',
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+966501234567',
                'form_id' => 'f1',
                'ad_id' => 'a1',
                'campaign_id' => 'c1',
                'created_time' => '2026-01-15T10:00:00Z',
            ],
            [
                'platform' => 'tiktok',
                'lead_id' => 'tt_lead_2',
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '',
                'form_id' => 'f2',
                'ad_id' => 'a2',
                'campaign_id' => 'c2',
                'created_time' => '2026-01-16',
            ],
        ];

        $result = $this->service->sync($rows);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $metaLead = Lead::where('campaign_platform', 'meta')->where('platform_lead_id', 'meta_lead_1')->first();
        $this->assertNotNull($metaLead);
        $this->assertSame('John Doe', $metaLead->name);
        $this->assertSame('+966501234567', $metaLead->contact_info); // phone preferred over email
        $this->assertSame('ads_meta', $metaLead->source);
        $this->assertSame('new', $metaLead->status);

        $ttLead = Lead::where('campaign_platform', 'tiktok')->where('platform_lead_id', 'tt_lead_2')->first();
        $this->assertNotNull($ttLead);
        $this->assertSame('jane@example.com', $ttLead->contact_info);
    }

    public function test_sync_skips_duplicates_on_second_call(): void
    {
        $rows = [
            [
                'platform' => 'meta',
                'lead_id' => 'dup_1',
                'name' => 'Dup',
                'email' => 'dup@example.com',
                'phone' => '',
                'form_id' => '',
                'ad_id' => '',
                'campaign_id' => '',
                'created_time' => '',
            ],
        ];

        $first = $this->service->sync($rows);
        $this->assertSame(1, $first['created']);

        $second = $this->service->sync($rows);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);

        $this->assertSame(1, Lead::where('campaign_platform', 'meta')->where('platform_lead_id', 'dup_1')->count());
    }

    public function test_sync_skips_rows_without_platform_or_lead_id(): void
    {
        $rows = [
            ['platform' => 'meta', 'lead_id' => '', 'name' => 'A', 'email' => 'a@x.com', 'phone' => '', 'form_id' => '', 'ad_id' => '', 'campaign_id' => '', 'created_time' => ''],
            ['platform' => '', 'lead_id' => 'id1', 'name' => 'B', 'email' => 'b@x.com', 'phone' => '', 'form_id' => '', 'ad_id' => '', 'campaign_id' => '', 'created_time' => ''],
            ['platform' => 'snap', 'lead_id' => 'snap_1', 'name' => 'C', 'email' => 'c@x.com', 'phone' => '', 'form_id' => '', 'ad_id' => '', 'campaign_id' => '', 'created_time' => ''],
        ];

        $result = $this->service->sync($rows);

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, Lead::where('campaign_platform', 'snap')->where('platform_lead_id', 'snap_1')->count());
    }

    public function test_sync_uses_phone_or_email_for_contact_info(): void
    {
        $rows = [
            [
                'platform' => 'meta',
                'lead_id' => 'ph1',
                'name' => 'Phone First',
                'email' => 'e@x.com',
                'phone' => '+966500000001',
                'form_id' => '',
                'ad_id' => '',
                'campaign_id' => '',
                'created_time' => '',
            ],
        ];

        $this->service->sync($rows);
        $lead = Lead::where('platform_lead_id', 'ph1')->first();
        $this->assertSame('+966500000001', $lead->contact_info);
    }
}
