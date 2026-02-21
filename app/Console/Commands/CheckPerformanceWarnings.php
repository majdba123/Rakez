<?php

namespace App\Console\Commands;

use App\Services\HR\MarketerPerformanceService;
use Illuminate\Console\Command;

class CheckPerformanceWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:check-performance-warnings 
                            {--threshold=50 : Minimum achievement rate threshold (percentage)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check marketer performance and issue auto-warnings for those below threshold';

    /**
     * Execute the console command.
     */
    public function handle(MarketerPerformanceService $performanceService): int
    {
        $threshold = (float) $this->option('threshold');

        $this->info("Checking marketer performance (threshold: {$threshold}%)...");

        try {
            $warningsIssued = $performanceService->checkAndIssueAutoWarnings($threshold);

            if ($warningsIssued > 0) {
                $this->info("Issued {$warningsIssued} auto-warning(s) for poor performance.");
            } else {
                $this->info('No warnings issued. All marketers meet the performance threshold.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error checking performance: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

