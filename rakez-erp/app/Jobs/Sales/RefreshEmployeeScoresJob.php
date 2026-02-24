<?php

namespace App\Jobs\Sales;

use App\Models\EmployeePerformanceScore;
use App\Services\Sales\AI\EmployeeSuccessScorer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshEmployeeScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function handle(EmployeeSuccessScorer $scorer): void
    {
        $periodEnd = now()->toDateString();
        $periodStart = now()->subMonths(3)->toDateString();

        Log::channel('ads')->info('RefreshEmployeeScores: Starting score refresh', [
            'period' => "{$periodStart} to {$periodEnd}",
        ]);

        $scores = $scorer->scoreAll($periodStart, $periodEnd);

        foreach ($scores as $card) {
            EmployeePerformanceScore::updateOrCreate(
                [
                    'user_id' => $card->userId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ],
                [
                    'composite_score' => $card->compositeScore,
                    'factor_scores' => $card->factorScores,
                    'strengths' => $card->strengths,
                    'weaknesses' => $card->weaknesses,
                    'trend' => $card->trend,
                    'project_type_affinity' => $card->projectTypeAffinity,
                ],
            );
        }

        Log::channel('ads')->info('RefreshEmployeeScores: Completed', [
            'employees_scored' => $scores->count(),
        ]);
    }
}
