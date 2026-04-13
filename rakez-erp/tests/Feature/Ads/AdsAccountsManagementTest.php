<?php

namespace Tests\Feature\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;
use Tests\Traits\TestsWithPermissions;

class AdsAccountsManagementTest extends TestCase
{
    use RefreshDatabase, TestsWithAds, TestsWithPermissions;

    public function test_manage_permission_required_to_upsert_account(): void
    {
        $this->ensurePermission('marketing.ads.manage');

        // The default seed assigns `marketing.ads.manage` to the `marketing` role.
        // Explicitly revoke it here to validate that the permission middleware blocks access.
        $marketingRole = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        $marketingRole->revokePermissionTo('marketing.ads.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->createMarketingUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/accounts', [
                'platform' => 'meta',
                'account_id' => 'act_111',
                'access_token' => 'token',
            ]);

        $response->assertStatus(403);
    }

    public function test_upsert_creates_platform_account(): void
    {
        $user = $this->createMarketingUser();
        $this->grantPermissions($user, ['marketing.ads.manage']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/ads/accounts', [
                'platform' => 'meta',
                'account_id' => 'act_111',
                'account_name' => 'Meta Account',
                'access_token' => 'token-meta',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('platform', 'meta')
            ->assertJsonPath('account_id', 'act_111');

        $this->assertDatabaseHas('ads_platform_accounts', [
            'platform' => 'meta',
            'account_id' => 'act_111',
            'account_name' => 'Meta Account',
            'is_active' => 1,
        ]);

        $row = AdsPlatformAccount::where('platform', 'meta')->where('account_id', 'act_111')->first();
        $this->assertNotNull($row);
        $this->assertSame('token-meta', $row->access_token);
    }
}
