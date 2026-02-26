<?php

namespace Tests\Feature\Ads;

use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class AdsOutboxCommandTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    public function test_status_displays_table(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        $this->seedOutcomeEvents('snap', 'delivered', 3);

        $this->artisan('ads:outbox', ['action' => 'status'])
            ->assertExitCode(0);
    }

    public function test_status_filters_by_platform(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        $this->seedOutcomeEvents('snap', 'delivered', 3);

        $this->artisan('ads:outbox', ['action' => 'status', '--platform' => 'meta'])
            ->assertExitCode(0);
    }

    public function test_replay_failed_resets_retry_count(): void
    {
        Queue::fake();
        $this->seedOutcomeEvents('meta', 'pending', 3);
        AdsOutcomeEvent::query()->update(['retry_count' => 3, 'last_error' => 'timeout']);

        $this->artisan('ads:outbox', ['action' => 'replay-failed'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ads_outcome_events', ['retry_count' => 3]);
        $this->assertDatabaseHas('ads_outcome_events', ['retry_count' => 0, 'last_error' => null]);
    }

    public function test_replay_dead_letter_moves_to_pending(): void
    {
        Queue::fake();
        $this->seedOutcomeEvents('meta', 'pending', 2);
        AdsOutcomeEvent::query()->update(['status' => 'dead_letter', 'retry_count' => 10]);

        $this->artisan('ads:outbox', ['action' => 'replay-dead-letter'])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ads_outcome_events', ['status' => 'dead_letter']);
        $this->assertDatabaseHas('ads_outcome_events', ['status' => 'pending', 'retry_count' => 0]);
    }

    public function test_purge_delivered_deletes_old_events(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        AdsOutcomeEvent::query()->update([
            'status' => 'delivered',
            'updated_at' => now()->subDays(60),
        ]);

        $this->artisan('ads:outbox', ['action' => 'purge-delivered', '--days' => '30'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('ads_outcome_events', 0);
    }

    public function test_purge_delivered_keeps_recent_events(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        AdsOutcomeEvent::query()->update(['status' => 'delivered']);

        $this->artisan('ads:outbox', ['action' => 'purge-delivered', '--days' => '30'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('ads_outcome_events', 2);
    }
}
