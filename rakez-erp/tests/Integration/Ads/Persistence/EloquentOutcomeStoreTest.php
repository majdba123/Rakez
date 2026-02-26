<?php

namespace Tests\Integration\Ads\Persistence;

use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\EloquentOutcomeStore;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class EloquentOutcomeStoreTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    private EloquentOutcomeStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new EloquentOutcomeStore();
    }

    public function test_enqueue_creates_rows_per_platform(): void
    {
        $event = $this->createOutcomeEvent();

        $this->store->enqueue($event);

        $this->assertDatabaseCount('ads_outcome_events', 3);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'meta', 'status' => 'pending']);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'snap', 'status' => 'pending']);
        $this->assertDatabaseHas('ads_outcome_events', ['platform' => 'tiktok', 'status' => 'pending']);
    }

    public function test_enqueue_idempotent_same_event_id(): void
    {
        $event = $this->createOutcomeEvent(['eventId' => 'fixed_evt_id']);

        $this->store->enqueue($event);
        $this->store->enqueue($event);

        $this->assertDatabaseCount('ads_outcome_events', 3);
    }

    public function test_fetch_pending_returns_only_pending(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        $this->seedOutcomeEvents('meta', 'delivered', 3);

        $pending = $this->store->fetchPending(10);

        $this->assertCount(2, $pending);
    }

    public function test_fetch_pending_ordered_by_created_at(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 3);

        $pending = $this->store->fetchPending(10);

        $dates = array_map(fn ($e) => $e->occurredAt->timestamp, $pending);
        $sorted = $dates;
        sort($sorted);

        $this->assertSame($sorted, $dates);
    }

    public function test_fetch_pending_respects_limit(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 10);

        $pending = $this->store->fetchPending(3);

        $this->assertCount(3, $pending);
    }

    public function test_mark_delivered_updates_status_and_response(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 1);
        $row = AdsOutcomeEvent::first();

        $this->store->markDelivered($row->event_id, 'meta', ['events_received' => 1]);

        $this->assertDatabaseHas('ads_outcome_events', [
            'event_id' => $row->event_id,
            'status' => 'delivered',
        ]);

        $updated = AdsOutcomeEvent::where('event_id', $row->event_id)->first();
        $this->assertSame(1, $updated->platform_response['events_received']);
        $this->assertNotNull($updated->last_attempted_at);
    }

    public function test_mark_failed_increments_retry_and_stores_error(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 1);
        $row = AdsOutcomeEvent::first();

        $this->store->markFailed($row->event_id, 'meta', 'HTTP 429 rate limited');

        $updated = AdsOutcomeEvent::where('event_id', $row->event_id)->first();
        $this->assertSame(1, $updated->retry_count);
        $this->assertSame('HTTP 429 rate limited', $updated->last_error);
        $this->assertSame('pending', $updated->status);
    }

    public function test_move_to_dead_letter(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);

        AdsOutcomeEvent::query()->update(['retry_count' => 6]);

        $moved = $this->store->moveToDeadLetter(5);

        $this->assertSame(2, $moved);
        $this->assertDatabaseMissing('ads_outcome_events', ['status' => 'pending']);
        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'dead_letter']);
    }

    public function test_move_to_dead_letter_leaves_low_retry_alone(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);

        AdsOutcomeEvent::first()->update(['retry_count' => 2]);
        AdsOutcomeEvent::orderBy('id', 'desc')->first()->update(['retry_count' => 6]);

        $moved = $this->store->moveToDeadLetter(5);

        $this->assertSame(1, $moved);
        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'pending', 'retry_count' => 2]);
    }
}
