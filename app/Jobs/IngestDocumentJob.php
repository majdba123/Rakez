<?php

namespace App\Jobs;

use App\Models\AiDocument;
use App\Services\AI\AiIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $meta  Must include 'access' => ['permissions_any_of' => [...]] for RAG.
     */
    public function __construct(
        private readonly int $documentId,
        private readonly string $content,
        private readonly array $meta = []
    ) {}

    public function handle(AiIndexingService $indexingService): void
    {
        $doc = AiDocument::find($this->documentId);
        if (! $doc) {
            Log::warning('IngestDocumentJob: document not found', ['document_id' => $this->documentId]);
            return;
        }

        $meta = array_merge($this->meta, [
            'type' => 'document',
            'access' => $this->meta['access'] ?? [
                'permissions_any_of' => ['use-ai-assistant'],
            ],
        ]);
        $indexingService->indexDocument($this->documentId, $this->content, $meta);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('IngestDocumentJob failed', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
