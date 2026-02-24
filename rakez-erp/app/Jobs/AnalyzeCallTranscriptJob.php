<?php

namespace App\Jobs;

use App\Models\AiCall;
use App\Models\Lead;
use App\Services\AI\Calling\CallConversationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeCallTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly int $aiCallId,
    ) {}

    public function handle(CallConversationEngine $engine): void
    {
        $call = AiCall::with('messages')->find($this->aiCallId);

        if (! $call) {
            Log::warning('AnalyzeCallTranscriptJob: call not found', ['ai_call_id' => $this->aiCallId]);
            return;
        }

        if ($call->messages->isEmpty()) {
            Log::info('AnalyzeCallTranscriptJob: no messages to analyze', ['ai_call_id' => $this->aiCallId]);
            return;
        }

        $summary = $engine->generateCallSummary($call);
        $call->update(['call_summary' => $summary]);

        $qualification = $engine->qualifyLead($call);
        $call->update(['sentiment_score' => $qualification['score'] / 100]);

        $this->updateLeadRecord($call, $qualification);

        Log::info('AI call analyzed', [
            'ai_call_id' => $call->id,
            'qualification' => $qualification['qualification'],
            'score' => $qualification['score'],
        ]);
    }

    private function updateLeadRecord(AiCall $call, array $qualification): void
    {
        if (! $call->lead_id) {
            return;
        }

        $lead = Lead::find($call->lead_id);
        if (! $lead) {
            return;
        }

        $updates = [
            'last_ai_call_id' => $call->id,
            'ai_call_count' => ($lead->ai_call_count ?? 0) + 1,
            'ai_qualification_status' => $qualification['qualification'],
            'ai_call_notes' => $qualification['notes'],
        ];

        $lead->update(array_filter($updates));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AnalyzeCallTranscriptJob failed', [
            'ai_call_id' => $this->aiCallId,
            'error' => $exception->getMessage(),
        ]);
    }
}
