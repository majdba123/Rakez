<?php

namespace Tests\Feature\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsLeadSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsLeadsStoredEndpointTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_stored_leads_endpoint_returns_paginated_rows(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        AdsLeadSubmission::create([
            'platform' => 'meta',
            'account_id' => 'act_111',
            'lead_id' => 'l1',
            'campaign_id' => 'c1',
            'created_time' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads/stored?platform=meta&per_page=50');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page']);
    }

    public function test_stored_leads_endpoint_filters_by_campaign(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.view']);

        AdsLeadSubmission::create([
            'platform' => 'meta',
            'account_id' => 'act_111',
            'lead_id' => 'l1',
            'campaign_id' => 'c1',
            'created_time' => now(),
        ]);
        AdsLeadSubmission::create([
            'platform' => 'meta',
            'account_id' => 'act_111',
            'lead_id' => 'l2',
            'campaign_id' => 'c2',
            'created_time' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/leads/stored?platform=meta&campaign_id=c2');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('c2', $data[0]['campaign_id']);
    }
}

