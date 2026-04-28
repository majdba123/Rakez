<?php

namespace Tests\Unit\Ads;

use App\Domain\Ads\ValueObjects\DateRange;
use App\Infrastructure\Ads\Meta\MetaClient;
use App\Infrastructure\Ads\Meta\MetaInsightsReader;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class MetaInsightsReaderTest extends TestCase
{
    public function test_fetch_insights_sets_spend_currency_from_account_currency(): void
    {
        $client = $this->createMock(MetaClient::class);

        $client->method('paginate')->willReturn((function () {
            yield [
                'campaign_id' => 'cmp_1',
                'date_start' => '2026-01-01',
                'date_stop' => '2026-01-01',
                'impressions' => '100',
                'clicks' => '5',
                'spend' => '12.34',
                'account_currency' => 'AED',
                'actions' => [
                    ['action_type' => 'lead', 'value' => '3'],
                    ['action_type' => 'purchase', 'value' => '1'],
                ],
                'action_values' => [
                    ['action_type' => 'purchase', 'value' => '99.99'],
                ],
                'reach' => '70',
                'video_30_sec_watched_actions' => [
                    ['value' => '10'],
                ],
            ];
        })());

        $reader = new MetaInsightsReader($client);
        $rows = $reader->fetchInsights(
            'act_111',
            'campaign',
            new DateRange(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-01')),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('cmp_1', $rows[0]['entity_id']);
        $this->assertSame('AED', $rows[0]['spend_currency']);
        $this->assertSame(3, $rows[0]['leads']);
        $this->assertSame(1, $rows[0]['conversions']);
        $this->assertSame(99.99, $rows[0]['revenue']);
    }

    public function test_list_campaigns_uses_account_id_as_given(): void
    {
        $client = $this->createMock(MetaClient::class);

        $client->expects($this->once())
            ->method('paginate')
            ->with(
                $this->equalTo('act_111/campaigns'),
                $this->arrayHasKey('fields'),
                $this->equalTo('act_111'),
            )
            ->willReturn((function () {
                yield ['id' => 'c1', 'name' => 'Camp', 'status' => 'ACTIVE', 'objective' => 'LEAD_GENERATION'];
            })());

        $reader = new MetaInsightsReader($client);
        $campaigns = $reader->listCampaigns('act_111');

        $this->assertSame('c1', $campaigns[0]['id']);
    }
}

