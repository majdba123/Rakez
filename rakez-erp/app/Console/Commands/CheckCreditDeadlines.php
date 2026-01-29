<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Credit\CreditFinancingService;

class CheckCreditDeadlines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credit:check-deadlines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check financing tracker stages and mark overdue ones';

    /**
     * Execute the console command.
     */
    public function handle(CreditFinancingService $financingService): int
    {
        $this->info('Checking credit financing deadlines...');

        try {
            $overdueCount = $financingService->markOverdueStages();

            if ($overdueCount > 0) {
                $this->info("Marked {$overdueCount} stage(s) as overdue.");
            } else {
                $this->info('No overdue stages found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error checking deadlines: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

