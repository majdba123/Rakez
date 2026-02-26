<?php

namespace Tests\Feature\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsOutcomeEndpointTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_store_outcome_returns_201_with_event_id(): void
    {
        $user = $this->createMarketingUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/outcomes', [
                'customer_id' => 'cust_001',
                'outcome_type' => 'PURCHASE',
                'occurred_at' => '2026-01-15 10:00:00',
                'email' => 'buyer@test.com',
                'value' => 500,
                'currency' => 'USD',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['event_id', 'outcome_type', 'platforms', 'status'])
            ->assertJson(['outcome_type' => 'PURCHASE', 'status' => 'queued']);
    }

    public function test_store_outcome_creates_outbox_rows(): void
    {
        $user = $this->createMarketingUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/outcomes', [
                'customer_id' => 'cust_002',
                'outcome_type' => 'LEAD_QUALIFIED',
                'occurred_at' => '2026-01-15',
                'email' => 'lead@test.com',
                'score' => 85,
            ]);

        $this->assertDatabaseCount('ads_outcome_events', 3);
    }

    public function test_store_outcome_validation_rejects_missing_fields(): void
    {
        $user = $this->createMarketingUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/outcomes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'outcome_type', 'occurred_at']);
    }

    public function test_store_outcome_validation_rejects_invalid_outcome_type(): void
    {
        $user = $this->createMarketingUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/outcomes', [
                'customer_id' => 'cust_003',
                'outcome_type' => 'INVALID_TYPE',
                'occurred_at' => '2026-01-15',
            ]);

        $response->assertStatus(422);
    }

    public function test_store_outcome_rejects_unauthenticated(): void
    {
        $response = $this->postJson('/api/ads/outcomes', [
            'customer_id' => 'cust_004',
            'outcome_type' => 'PURCHASE',
            'occurred_at' => '2026-01-15',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_outcome_rejects_non_marketing_role(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/outcomes', [
                'customer_id' => 'cust_005',
                'outcome_type' => 'PURCHASE',
                'occurred_at' => '2026-01-15',
            ]);

        $response->assertStatus(403);
    }

    public function test_status_endpoint_returns_summary(): void
    {
        $user = $this->createMarketingUser();
        $this->seedOutcomeEvents('meta', 'pending', 2);
        $this->seedOutcomeEvents('meta', 'delivered', 3);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/outcomes/status');

        $response->assertOk()
            ->assertJsonStructure(['summary', 'events']);

        $summary = $response->json('summary');
        $this->assertArrayHasKey('pending', $summary);
        $this->assertArrayHasKey('delivered', $summary);
    }

    public function test_status_endpoint_filters_by_platform(): void
    {
        $user = $this->createMarketingUser();
        $this->seedOutcomeEvents('meta', 'pending', 2);
        $this->seedOutcomeEvents('snap', 'pending', 3);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/ads/outcomes/status?platform=snap');

        $response->assertOk();
        $events = $response->json('events');
        foreach ($events as $event) {
            $this->assertSame('snap', $event['platform']);
        }
    }
}
