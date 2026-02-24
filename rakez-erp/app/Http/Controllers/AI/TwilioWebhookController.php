<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCallTranscriptJob;
use App\Models\AiCall;
use App\Services\AI\Calling\CallConversationEngine;
use App\Services\AI\Calling\TwilioVoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class TwilioWebhookController extends Controller
{
    public function __construct(
        private readonly TwilioVoiceService $twilio,
        private readonly CallConversationEngine $engine,
    ) {}

    /**
     * POST /webhooks/twilio/voice/{callId}
     * Called when the outbound call is answered. Delivers greeting + first question.
     */
    public function handleVoice(Request $request, int $callId): Response
    {
        $call = AiCall::find($callId);

        if (! $call) {
            Log::error('Twilio webhook: call not found', ['call_id' => $callId]);
            return $this->twimlResponse($this->twilio->hangup());
        }

        $answeredBy = $request->input('AnsweredBy', 'human');
        if (in_array($answeredBy, ['machine_start', 'machine_end_beep', 'machine_end_silence', 'fax'])) {
            Log::info('AI call answered by machine, hanging up', ['ai_call_id' => $callId]);
            $call->markAsFailed('answered_by_machine');
            return $this->twimlResponse($this->twilio->hangup());
        }

        $call->markAsInProgress();

        try {
            $greeting = $this->engine->buildGreeting($call);

            $twiml = $this->twilio->generateGatherTwiml(
                $greeting['ai_response'],
                $callId,
                $greeting['question_key'] ?? '',
            );

            return $this->twimlResponse($twiml);
        } catch (Throwable $e) {
            Log::error('Twilio voice webhook failed', [
                'ai_call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            $call->markAsFailed('voice_handler_error');
            return $this->twimlResponse($this->twilio->hangup());
        }
    }

    /**
     * POST /webhooks/twilio/gather/{callId}
     * Called when Twilio captures the client's speech (STT result).
     */
    public function handleGather(Request $request, int $callId): Response
    {
        $call = AiCall::find($callId);

        if (! $call || $call->isFinished()) {
            return $this->twimlResponse($this->twilio->hangup());
        }

        $speechResult = $request->input('SpeechResult', '');
        $confidence = (float) $request->input('Confidence', 0);
        $questionKey = $request->query('qk', '');

        Log::info('Twilio gather received', [
            'ai_call_id' => $callId,
            'speech' => mb_substr($speechResult, 0, 200),
            'confidence' => $confidence,
            'question_key' => $questionKey,
        ]);

        if (empty(trim($speechResult))) {
            return $this->handleFallback($request, $callId);
        }

        try {
            $result = $this->engine->processClientResponse($call, $speechResult, $questionKey ?: null);

            if ($result['is_complete']) {
                $closingTwiml = $this->twilio->generateClosingTwiml($result['ai_response']);
                $call->markAsCompleted();
                AnalyzeCallTranscriptJob::dispatch($call->id);
                return $this->twimlResponse($closingTwiml);
            }

            $twiml = $this->twilio->generateGatherTwiml(
                $result['ai_response'],
                $callId,
                $result['question_key'] ?? '',
            );

            return $this->twimlResponse($twiml);
        } catch (Throwable $e) {
            Log::error('Twilio gather handler failed', [
                'ai_call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            $call->markAsFailed('gather_handler_error');
            return $this->twimlResponse(
                $this->twilio->generateClosingTwiml('حصل خطأ تقني. بنتواصل معاك لاحقاً. مع السلامة.')
            );
        }
    }

    /**
     * POST /webhooks/twilio/status/{callId}
     * Call status callback (tracks ringing, answered, completed, failed, etc.).
     */
    public function handleStatus(Request $request, int $callId): Response
    {
        $call = AiCall::find($callId);
        if (! $call) {
            return response('OK', 200);
        }

        $callStatus = $request->input('CallStatus', '');
        $callSid = $request->input('CallSid', '');
        $duration = (int) $request->input('CallDuration', 0);

        Log::info('Twilio status callback', [
            'ai_call_id' => $callId,
            'twilio_status' => $callStatus,
            'call_sid' => $callSid,
            'duration' => $duration,
        ]);

        if ($callSid && ! $call->twilio_call_sid) {
            $call->update(['twilio_call_sid' => $callSid]);
        }

        match ($callStatus) {
            'ringing' => $call->status === 'pending' ? $call->update(['status' => 'ringing']) : null,
            'in-progress' => null,
            'completed' => $this->handleCallCompleted($call, $duration),
            'busy' => $call->update(['status' => 'busy', 'ended_at' => now()]),
            'no-answer' => $call->update(['status' => 'no_answer', 'ended_at' => now()]),
            'failed' => $call->markAsFailed('twilio_failed'),
            'canceled' => $call->update(['status' => 'cancelled', 'ended_at' => now()]),
            default => null,
        };

        return response('OK', 200);
    }

    /**
     * POST /webhooks/twilio/fallback/{callId}
     * Called when client doesn't speak (silence timeout in <Gather>).
     */
    public function handleFallback(Request $request, int $callId): Response
    {
        $call = AiCall::find($callId);

        if (! $call || $call->isFinished()) {
            return $this->twimlResponse($this->twilio->hangup());
        }

        $questionKey = $request->query('qk', '');

        try {
            $result = $this->engine->handleNoResponse($call, $questionKey ?: null);

            if ($result['is_complete']) {
                $closingTwiml = $this->twilio->generateClosingTwiml($result['ai_response']);
                $call->markAsCompleted();
                AnalyzeCallTranscriptJob::dispatch($call->id);
                return $this->twimlResponse($closingTwiml);
            }

            $twiml = $this->twilio->generateGatherTwiml(
                $result['ai_response'],
                $callId,
                $result['question_key'] ?? '',
            );

            return $this->twimlResponse($twiml);
        } catch (Throwable $e) {
            Log::error('Twilio fallback handler failed', [
                'ai_call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            $call->markAsFailed('fallback_handler_error');
            return $this->twimlResponse($this->twilio->hangup());
        }
    }

    private function handleCallCompleted(AiCall $call, int $duration): void
    {
        if ($call->status !== 'completed') {
            $call->update([
                'status' => 'completed',
                'ended_at' => now(),
                'duration_seconds' => $duration ?: $call->duration_seconds,
            ]);

            AnalyzeCallTranscriptJob::dispatch($call->id);
        } else {
            if ($duration > 0 && ! $call->duration_seconds) {
                $call->update(['duration_seconds' => $duration]);
            }
        }
    }

    private function twimlResponse($twiml): Response
    {
        return response((string) $twiml, 200, [
            'Content-Type' => 'text/xml',
        ]);
    }
}
