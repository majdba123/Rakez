<?php

namespace App\Console\Commands;

use App\Services\Sales\NegotiationApprovalService;
use Illuminate\Console\Command;

class ExpireNegotiationApprovals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'negotiations:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire overdue negotiation approvals (past 48-hour deadline)';

    /**
     * Execute the console command.
     */
    public function handle(NegotiationApprovalService $approvalService): int
    {
        $this->info('Checking for overdue negotiation approvals...');

        $count = $approvalService->expireOverdue();

        if ($count > 0) {
            $this->info("Expired {$count} negotiation approval(s).");
        } else {
            $this->info('No overdue approvals found.');
        }

        return Command::SUCCESS;
    }
}

