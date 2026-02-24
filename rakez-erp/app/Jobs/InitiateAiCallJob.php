<?php

namespace App\Jobs;

use App\Models\AiCall;
use App\Services\AI\Calling\TwilioVoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InitiateAiCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;
    public int $backoff = 60;

    public function __construct(
        private readonly int $aiCallId,
    ) {}

    public function handle(TwilioVoiceService $twilioService): void
    {
        $call = AiCall::find($this->aiCallId);

        if (! $call) {
            Log::warning('InitiateAiCallJob: call record not found', ['ai_call_id' => $this->aiCallId]);
            return;
        }

        if ($call->status !== 'pending') {
            Log::info('InitiateAiCallJob: call already processed', [
                'ai_call_id' => $this->aiCallId,
                'status' => $call->status,
            ]);
            return;
        }

        if (! config('ai_calling.enabled')) {
            $call->markAsFailed('ai_calling_disabled');
            return;
        }

        try {
            $callSid = $twilioService->initiateCall(
                $call->phone_number,
                $call->id,
            );

            $call->markAsRinging($callSid);

            Log::info('AI call placed via Twilio', [
                'ai_call_id' => $call->id,
                'call_sid' => $callSid,
                'phone' => $call->phone_number,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to initiate AI call via Twilio', [
                'ai_call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            $call->markAsFailed($e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('InitiateAiCallJob permanently failed', [
            'ai_call_id' => $this->aiCallId,
            'error' => $exception->getMessage(),
        ]);

        $call = AiCall::find($this->aiCallId);
        $call?->markAsFailed('job_failed: ' . $exception->getMessage());
    }
}
