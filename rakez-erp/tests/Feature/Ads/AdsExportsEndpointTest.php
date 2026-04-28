<?php

namespace Tests\Feature\Ads;

use App\Jobs\Ads\GenerateAdsExportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsExportsEndpointTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_create_leads_csv_export_dispatches_job(): void
    {
        Queue::fake();
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $this->createPlatformAccount('meta', 'act_111');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/exports/leads', [
                'platform' => 'meta',
                'account_id' => 'act_111',
                'campaign_id' => 'camp_1',
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['export_id', 'status']);

        Queue::assertPushed(GenerateAdsExportJob::class, 1);
    }

    public function test_export_download_returns_409_when_not_ready(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $export = \App\Infrastructure\Ads\Persistence\Models\AdsExport::create([
            'type' => 'leads_csv',
            'status' => 'queued',
            'filters' => ['platform' => 'meta', 'account_id' => 'act_111'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/ads/exports/{$export->id}/download");

        $response->assertStatus(409);
    }

    public function test_export_download_returns_file_when_completed(): void
    {
        Storage::fake('local');
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $path = 'exports/ads/leads/test.csv';
        Storage::disk('local')->put($path, "platform,lead_id\nmeta,1\n");

        $export = \App\Infrastructure\Ads\Persistence\Models\AdsExport::create([
            'type' => 'leads_csv',
            'status' => 'completed',
            'filters' => ['platform' => 'meta', 'account_id' => 'act_111'],
            'storage_disk' => 'local',
            'storage_path' => $path,
            'download_filename' => 'leads.csv',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/ads/exports/{$export->id}/download");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }
}
