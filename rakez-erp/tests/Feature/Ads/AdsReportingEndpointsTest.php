<?php

namespace Tests\Feature\Ads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsReportingEndpointsTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_platform_performance_endpoint_returns_data(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $this->seedInsightRows('meta', 3);
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/reports/platform-performance');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_campaign_performance_endpoint_requires_platform(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/reports/campaign-performance');

        $response->assertStatus(422);
    }
}

