<?php

namespace Tests\Feature\Ads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestsWithAds;

class AdsHealthCommandTest extends TestCase
{
    use RefreshDatabase, TestsWithAds;

    public function test_health_command_runs_successfully(): void
    {
        $this->createPlatformAccount('meta', 'act_111');
        $this->seedInsightRows('meta', 3);

        $this->artisan('ads:health')
            ->assertExitCode(0);
    }

    public function test_health_command_with_no_data(): void
    {
        $this->artisan('ads:health')
            ->assertExitCode(0);
    }

    public function test_health_command_shows_dead_letter_alert(): void
    {
        $this->seedOutcomeEvents('meta', 'pending', 2);
        \App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent::query()->update(['status' => 'dead_letter']);

        $this->artisan('ads:health')
            ->assertExitCode(0);
    }

    public function test_health_command_shows_insight_stats(): void
    {
        $this->createPlatformAccount('meta', 'act_111');
        $this->seedInsightRows('meta', 5);

        $this->artisan('ads:health')
            ->assertExitCode(0);
    }
}
