<?php

namespace App\Listeners\AI;

use App\Events\AI\AiDocumentIngested;
use App\Services\AI\AiAuditService;

class LogAiDocumentIngestedAudit
{
    public function __construct(
        private readonly AiAuditService $auditService,
    ) {}

    public function handle(AiDocumentIngested $event): void
    {
        $this->auditService->recordByUserId(
            $event->userId,
            'document_ingest',
            'ai_document',
            $event->documentId,
            [
                'title' => $event->title,
                'chunks' => $event->chunksCount,
                'tokens' => $event->totalTokens,
            ],
            [],
            $event->correlationId,
        );
    }
}
