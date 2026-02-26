<?php

namespace Tests\Traits;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\Models\AdsInsightRow;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use App\Infrastructure\Ads\Persistence\Models\AdsPlatformAccount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

trait TestsWithAds
{
    protected function createPlatformAccount(
        string $platform = 'meta',
        string $accountId = 'act_123456',
        array $overrides = [],
    ): AdsPlatformAccount {
        return AdsPlatformAccount::create(array_merge([
            'platform' => $platform,
            'account_id' => $accountId,
            'account_name' => "Test {$platform} account",
            'access_token' => 'test-token-' . $platform,
            'refresh_token' => 'test-refresh-' . $platform,
            'token_expires_at' => now()->addDay(),
            'is_active' => true,
        ], $overrides));
    }

    protected function createOutcomeEvent(array $overrides = []): OutcomeEvent
    {
        $defaults = [
            'eventId' => 'evt_' . md5(uniqid()),
            'outcomeType' => OutcomeType::Purchase,
            'occurredAt' => CarbonImmutable::parse('2026-01-15 10:00:00'),
            'identifiers' => [
                new HashedIdentifier('em', hash('sha256', 'test@example.com')),
                new HashedIdentifier('ph', hash('sha256', '1234567890')),
            ],
            'targetPlatforms' => [Platform::Meta, Platform::Snap, Platform::TikTok],
            'value' => new Money(99.99, 'USD'),
            'crmStage' => null,
            'score' => null,
            'leadId' => null,
            'metaFbc' => 'fb.1.123.abc',
            'metaFbp' => 'fb.1.456.def',
            'snapClickId' => 'sc_click_123',
            'snapCookie1' => 'sc_cookie_456',
            'tiktokTtclid' => 'tt_click_789',
            'tiktokTtp' => 'tt_ttp_012',
            'clientIp' => '192.168.1.1',
            'clientUserAgent' => 'Mozilla/5.0',
            'eventSourceUrl' => 'https://example.com/thank-you',
            'customData' => [],
        ];

        $merged = array_merge($defaults, $overrides);

        return new OutcomeEvent(...$merged);
    }

    protected function createOutcomeEventForCrmLead(array $overrides = []): OutcomeEvent
    {
        return $this->createOutcomeEvent(array_merge([
            'outcomeType' => OutcomeType::LeadQualified,
            'leadId' => '1234567890123456',
            'crmStage' => 'Marketing Qualified Lead',
            'score' => 85,
            'value' => null,
        ], $overrides));
    }

    protected function seedInsightRows(string $platform = 'meta', int $count = 5): void
    {
        for ($i = 0; $i < $count; $i++) {
            AdsInsightRow::create([
                'platform' => $platform,
                'account_id' => 'act_123456',
                'level' => 'campaign',
                'entity_id' => "camp_{$i}",
                'date_start' => now()->subDays($count - $i)->toDateString(),
                'date_stop' => now()->subDays($count - $i)->toDateString(),
                'breakdown_hash' => 'none',
                'impressions' => rand(1000, 50000),
                'clicks' => rand(50, 2000),
                'spend' => rand(10, 500) + (rand(0, 99) / 100),
                'spend_currency' => 'USD',
                'conversions' => rand(0, 100),
                'revenue' => rand(0, 5000) + (rand(0, 99) / 100),
                'video_views' => rand(0, 10000),
                'reach' => rand(500, 30000),
            ]);
        }
    }

    protected function seedOutcomeEvents(string $platform = 'meta', string $status = 'pending', int $count = 3): void
    {
        for ($i = 0; $i < $count; $i++) {
            AdsOutcomeEvent::create([
                'event_id' => 'evt_' . md5(uniqid() . $i),
                'platform' => $platform,
                'outcome_type' => OutcomeType::Purchase->value,
                'occurred_at' => now()->subHours($count - $i),
                'status' => $status,
                'retry_count' => $status === 'pending' ? 0 : rand(1, 5),
                'value' => 99.99,
                'currency' => 'USD',
                'hashed_identifiers' => [['type' => 'em', 'value' => hash('sha256', 'test@example.com')]],
                'click_ids' => ['meta_fbc' => 'fb.1.123.abc'],
                'payload' => ['client_ip' => '192.168.1.1'],
            ]);
        }
    }

    protected function createMarketingUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'marketing', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function createAdminUserForAds(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['type' => 'admin']);
        $user->assignRole($role);

        return $user;
    }
}
