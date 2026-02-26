<?php

namespace Tests\Integration\Ads\Application;

use App\Application\Ads\PublishOutcomeEvents;
use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\EloquentOutcomeStore;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class PublishOutcomeEventsTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    private EloquentOutcomeStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new EloquentOutcomeStore();
    }

    public function test_publish_marks_delivered_on_success(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);

        $mockWriter = $this->createMock(AdsWritePort::class);
        $mockWriter->method('sendEvent')->willReturn(['events_received' => 1]);

        $useCase = new PublishOutcomeEvents($this->store, ['meta' => $mockWriter]);
        $processed = $useCase->execute(50);

        $this->assertSame(2, $processed);
        $this->assertDatabaseMissing('ads_outcome_events', ['status' => 'pending', 'platform' => 'meta']);
        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'delivered']);
    }

    public function test_publish_marks_failed_on_exception(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 1);

        $mockWriter = $this->createMock(AdsWritePort::class);
        $mockWriter->method('sendEvent')->willThrowException(new \RuntimeException('API error 429'));

        $useCase = new PublishOutcomeEvents($this->store, ['meta' => $mockWriter]);
        $processed = $useCase->execute(50);

        $this->assertSame(0, $processed);

        $row = AdsOutcomeEvent::first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(1, $row->retry_count);
        $this->assertStringContainsString('429', $row->last_error);
    }

    public function test_dead_letter_promotion_before_publishing(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        AdsOutcomeEvent::query()->update(['retry_count' => 10]);

        $mockWriter = $this->createMock(AdsWritePort::class);
        $mockWriter->expects($this->never())->method('sendEvent');

        $useCase = new PublishOutcomeEvents($this->store, ['meta' => $mockWriter]);
        $useCase->execute(50);

        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'dead_letter']);
        $this->assertDatabaseMissing('ads_outcome_events', ['status' => 'pending']);
    }

    public function test_skips_platform_without_writer(): void
    {
        $this->seedOutcomeEvents('snap', 'pending', 1);

        $useCase = new PublishOutcomeEvents($this->store, []);
        $processed = $useCase->execute(50);

        $this->assertSame(0, $processed);
        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'pending']);
    }

    public function test_batch_size_limits_processing(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 5);

        $mockWriter = $this->createMock(AdsWritePort::class);
        $mockWriter->method('sendEvent')->willReturn(['ok' => true]);

        $useCase = new PublishOutcomeEvents($this->store, ['meta' => $mockWriter]);
        $processed = $useCase->execute(2);

        $this->assertSame(2, $processed);

        $remaining = AdsOutcomeEvent::where('status', 'pending')->count();
        $this->assertSame(3, $remaining);
    }
}
