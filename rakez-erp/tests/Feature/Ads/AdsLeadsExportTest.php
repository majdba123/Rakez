<?php

namespace Tests\Feature\Ads;

use App\Infrastructure\Ads\Meta\MetaClient;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsLeadsExportTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    /** Raw Meta API lead shape that MetaLeadGenReader normalizes. */
    private static function rawMetaLeadItem(): array
    {
        return [
            'id' => 'meta_lead_123',
            'created_time' => '2026-01-15T10:00:00Z',
            'form_id' => 'f1',
            'ad_id' => 'a1',
            'adset_id' => 'as1',
            'campaign_id' => 'c1',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Test User']],
                ['name' => 'email', 'values' => ['test@example.com']],
                ['name' => 'phone_number', 'values' => ['+966501234567']],
            ],
        ];
    }

    private function bindMetaClientReturningOneLead(): void
    {
        $mock = Mockery::mock(MetaClient::class);
        $mock->shouldReceive('paginate')->andReturnUsing(function () {
            yield self::rawMetaLeadItem();
        });
        $this->app->instance(MetaClient::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_leads_index_meta_without_form_id_or_ad_id_returns_422(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads?platform=meta');

        $response->assertStatus(422)
            ->assertJsonPath('message', 'For Meta, either form_id or ad_id is required.');
    }

    public function test_leads_index_tiktok_without_advertiser_id_returns_422(): void
    {
        config(['ads_platforms.tiktok.advertiser_id' => null]);
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads?platform=tiktok');

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'For TikTok, advertiser_id is required (query param or ads_platforms.tiktok.advertiser_id).']);
    }

    public function test_leads_index_returns_200_with_data_when_mock_returns_leads(): void
    {
        $this->bindMetaClientReturningOneLead();

        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads?platform=meta&form_id=123');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.platform', 'meta')
            ->assertJsonPath('data.0.lead_id', 'meta_lead_123')
            ->assertJsonPath('data.0.name', 'Test User');
    }

    public function test_leads_export_returns_excel_download(): void
    {
        $this->bindMetaClientReturningOneLead();

        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/ads/leads/export?platform=meta&form_id=123');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('leads_meta_', $response->headers->get('content-disposition'));
    }

    public function test_leads_export_snap_upload_csv_returns_excel(): void
    {
        $csv = "name,email,phone\nJohn,john@test.com,+966501234567";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/ads/leads/export-snap', ['csv' => $file]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('leads_snap_', $response->headers->get('content-disposition'));
    }

    public function test_leads_index_with_sync_creates_leads_in_database(): void
    {
        $this->bindMetaClientReturningOneLead();

        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads?platform=meta&form_id=123&sync=1');

        $lead = Lead::where('platform_lead_id', 'meta_lead_123')->where('campaign_platform', 'meta')->first();
        $this->assertNotNull($lead);
        $this->assertSame('Test User', $lead->name);
        $this->assertSame('ads_meta', $lead->source);
    }

    public function test_leads_endpoints_reject_unauthenticated(): void
    {
        $this->getJson('/api/ads/leads?platform=meta&form_id=123')->assertStatus(401);
        $this->getJson('/api/ads/leads/export?platform=meta&form_id=123')->assertStatus(401);
        $this->postJson('/api/ads/leads/export-snap')->assertStatus(401);
    }

    public function test_leads_endpoints_require_marketing_ads_permission(): void
    {
        $this->ensurePermission('marketing.ads.view');
        $user = \App\Models\User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']));

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads?platform=meta&form_id=123');

        $response->assertStatus(403);
    }
}
