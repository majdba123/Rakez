<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Services\AI\AiIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexRecordSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly string $module,
        private readonly int $recordId,
        private readonly bool $isDeleted = false
    ) {}

    public function handle(AiIndexingService $indexingService): void
    {
        if ($this->isDeleted) {
            $indexingService->markRecordDeleted($this->module, (string) $this->recordId);
            return;
        }

        $summary = $this->buildSummary();
        if ($summary === null) {
            return;
        }

        [$title, $content, $meta] = $summary;
        $indexingService->indexRecordSummary(
            $this->module,
            (string) $this->recordId,
            $title,
            $content,
            $meta
        );
    }

    /**
     * @return array{0: string, 1: string, 2: array}|null
     */
    private function buildSummary(): ?array
    {
        return match ($this->module) {
            'lead' => $this->buildLeadSummary(),
            'contract' => $this->buildContractSummary(),
            'marketing_task' => $this->buildMarketingTaskSummary(),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: string, 2: array}|null
     */
    private function buildLeadSummary(): ?array
    {
        $lead = Lead::find($this->recordId);
        if (! $lead) {
            return null;
        }
        $title = 'Lead: ' . ($lead->name ?? " #{$this->recordId}");
        $content = sprintf(
            'Lead #%d: %s. Contact: %s. Source: %s. Status: %s.',
            $lead->id,
            $lead->name ?? '—',
            mb_substr($lead->contact_info ?? '', 0, 200),
            $lead->source ?? '—',
            $lead->status ?? '—'
        );
        $meta = [
            'type' => 'record',
            'access' => [
                'permissions_any_of' => ['marketing.projects.view'],
                'policy' => [
                    'model' => Lead::class,
                    'ability' => 'view',
                    'record_id' => $lead->id,
                ],
            ],
        ];
        return [$title, $content, $meta];
    }

    /**
     * @return array{0: string, 1: string, 2: array}|null
     */
    private function buildContractSummary(): ?array
    {
        $contract = Contract::find($this->recordId);
        if (! $contract) {
            return null;
        }
        $title = 'Project: ' . ($contract->project_name ?? " #{$this->recordId}");
        $content = sprintf(
            'Project #%d: %s. Developer: %s. City: %s. Status: %s.',
            $contract->id,
            $contract->project_name ?? '—',
            $contract->developer_name ?? '—',
            $contract->city ?? '—',
            $contract->status ?? '—'
        );
        $meta = [
            'type' => 'record',
            'access' => [
                'permissions_any_of' => ['contracts.view', 'contracts.view_all'],
                'policy' => [
                    'model' => Contract::class,
                    'ability' => 'view',
                    'record_id' => $contract->id,
                ],
            ],
        ];
        return [$title, $content, $meta];
    }

    /**
     * @return array{0: string, 1: string, 2: array}|null
     */
    private function buildMarketingTaskSummary(): ?array
    {
        $task = MarketingTask::find($this->recordId);
        if (! $task) {
            return null;
        }
        $title = 'Task: ' . ($task->task_name ?? " #{$this->recordId}");
        $content = sprintf(
            'Marketing Task #%d: %s. Status: %s. Due: %s.',
            $task->id,
            $task->task_name ?? '—',
            $task->status ?? '—',
            $task->due_date?->toDateString() ?? '—'
        );
        $meta = [
            'type' => 'record',
            'access' => [
                'permissions_any_of' => ['marketing.projects.view'],
                'policy' => [
                    'model' => MarketingTask::class,
                    'ability' => 'view',
                    'record_id' => $task->id,
                ],
            ],
        ];
        return [$title, $content, $meta];
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('IndexRecordSummaryJob failed', [
            'module' => $this->module,
            'record_id' => $this->recordId,
            'error' => $exception->getMessage(),
        ]);
    }
}
