<?php

namespace App\Jobs\Sales;

use App\Services\Sales\SalesUnitSearchAlertMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchUnitSearchAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        private readonly int $contractUnitId,
    ) {}

    public function handle(SalesUnitSearchAlertMatchingService $matchingService): void
    {
        $matchingService->matchUnit($this->contractUnitId);
    }
}
