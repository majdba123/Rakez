<?php

namespace Tests\Integration\Ads\Persistence;

use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\EloquentTokenStore;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentTokenStoreTest extends TestCase
{
    use RefreshDatabase;

    private EloquentTokenStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new EloquentTokenStore();
    }

    public function test_save_and_get_access_token_round_trip(): void
    {
        $this->store->saveTokens(
            Platform::Meta,
            'act_123',
            'meta-token-abc',
            null,
            now()->addDay(),
        );

        $token = $this->store->getAccessToken(Platform::Meta, 'act_123');

        $this->assertSame('meta-token-abc', $token);
    }

    public function test_save_and_get_refresh_token(): void
    {
        $this->store->saveTokens(
            Platform::Snap,
            'snap_acc_1',
            'snap-access-token',
            'snap-refresh-token',
            now()->addHour(),
        );

        $refresh = $this->store->getRefreshToken(Platform::Snap, 'snap_acc_1');

        $this->assertSame('snap-refresh-token', $refresh);
    }

    public function test_returns_null_for_nonexistent_account(): void
    {
        $token = $this->store->getAccessToken(Platform::Meta, 'nonexistent');

        $this->assertNull($token);
    }

    public function test_returns_null_refresh_for_nonexistent_account(): void
    {
        $token = $this->store->getRefreshToken(Platform::TikTok, 'nonexistent');

        $this->assertNull($token);
    }

    public function test_save_tokens_upserts_on_duplicate(): void
    {
        $this->store->saveTokens(Platform::Meta, 'act_123', 'token-v1');
        $this->store->saveTokens(Platform::Meta, 'act_123', 'token-v2');

        $this->assertDatabaseCount('ads_platform_accounts', 1);
        $this->assertSame('token-v2', $this->store->getAccessToken(Platform::Meta, 'act_123'));
    }

    public function test_token_is_encrypted_in_database(): void
    {
        $this->store->saveTokens(Platform::Meta, 'act_123', 'my-secret-token');

        $raw = \DB::table('ads_platform_accounts')
            ->where('platform', 'meta')
            ->where('account_id', 'act_123')
            ->value('access_token');

        $this->assertNotSame('my-secret-token', $raw);
        $this->assertNotEmpty($raw);
    }

    public function test_different_platforms_same_account_id_are_separate(): void
    {
        $this->store->saveTokens(Platform::Meta, 'shared_id', 'meta-token');
        $this->store->saveTokens(Platform::Snap, 'shared_id', 'snap-token');

        $this->assertDatabaseCount('ads_platform_accounts', 2);
        $this->assertSame('meta-token', $this->store->getAccessToken(Platform::Meta, 'shared_id'));
        $this->assertSame('snap-token', $this->store->getAccessToken(Platform::Snap, 'shared_id'));
    }
}
