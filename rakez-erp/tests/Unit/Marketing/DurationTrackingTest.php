<?php

namespace Tests\Unit\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Marketing\MarketingProjectService;
use App\Models\ContractInfo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DurationTrackingTest extends TestCase
{
    use RefreshDatabase;

    private MarketingProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarketingProjectService();
    }

    #[Test]
    public function it_returns_red_status_for_less_than_30_days()
    {
        $info = ContractInfo::factory()->create([
            'agreement_duration_days' => 60,
            'created_at' => Carbon::now()->subDays(40) // 20 days remaining
        ]);

        $result = $this->service->getContractDurationStatus($info->contract_id);

        $this->assertEquals('red', $result['status']);
        $this->assertLessThan(30, $result['days']);
    }

    #[Test]
    public function it_returns_orange_status_for_30_to_90_days()
    {
        $info = ContractInfo::factory()->create([
            'agreement_duration_days' => 120,
            'created_at' => Carbon::now()->subDays(60) // 60 days remaining
        ]);

        $result = $this->service->getContractDurationStatus($info->contract_id);

        $this->assertEquals('orange', $result['status']);
        $this->assertGreaterThanOrEqual(30, $result['days']);
        $this->assertLessThanOrEqual(90, $result['days']);
    }

    #[Test]
    public function it_returns_green_status_for_more_than_90_days()
    {
        $info = ContractInfo::factory()->create([
            'agreement_duration_days' => 180,
            'created_at' => Carbon::now()->subDays(30) // 150 days remaining
        ]);

        $result = $this->service->getContractDurationStatus($info->contract_id);

        $this->assertEquals('green', $result['status']);
        $this->assertGreaterThan(90, $result['days']);
    }

    #[Test]
    public function it_returns_green_status_for_exactly_90_days()
    {
        Carbon::setTestNow(Carbon::parse('2026-02-01 00:00:00'));

        $info = ContractInfo::factory()->create([
            'agreement_duration_days' => 120,
            'created_at' => Carbon::now()->subDays(30) // 90 days remaining
        ]);

        $result = $this->service->getContractDurationStatus($info->contract_id);

        $this->assertEquals('green', $result['status']);
        $this->assertEquals(90, $result['days']);
        Carbon::setTestNow();
    }
}
