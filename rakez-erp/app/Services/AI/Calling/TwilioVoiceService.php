<?php

namespace App\Services\AI\Calling;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\TwiML\VoiceResponse;
use Throwable;

class TwilioVoiceService
{
    private ?TwilioClient $client = null;

    private function client(): TwilioClient
    {
        if ($this->client === null) {
            $this->client = new TwilioClient(
                config('ai_calling.twilio.sid'),
                config('ai_calling.twilio.token')
            );
        }

        return $this->client;
    }

    private function webhookUrl(string $path): string
    {
        return rtrim(config('ai_calling.twilio.webhook_base_url'), '/') . '/api' . $path;
    }

    /**
     * Initiate an outbound call via Twilio.
     *
     * @return string The Twilio Call SID
     */
    public function initiateCall(string $to, int $callId): string
    {
        $call = $this->client()->calls->create(
            $to,
            config('ai_calling.twilio.from_number'),
            [
                'url' => $this->webhookUrl("/webhooks/twilio/voice/{$callId}"),
                'statusCallback' => $this->webhookUrl("/webhooks/twilio/status/{$callId}"),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'method' => 'POST',
                'timeout' => 30,
                'machineDetection' => 'Enable',
            ]
        );

        Log::info('Twilio call initiated', [
            'call_sid' => $call->sid,
            'to' => $to,
            'ai_call_id' => $callId,
        ]);

        return $call->sid;
    }

    /**
     * Generate TwiML that speaks text and gathers speech input.
     */
    public function generateGatherTwiml(string $sayText, int $callId, string $questionKey = ''): VoiceResponse
    {
        $response = new VoiceResponse();
        $language = config('ai_calling.call.language', 'ar-SA');
        $voice = config('ai_calling.call.voice', 'Polly.Zeina');

        $gather = $response->gather([
            'input' => 'speech',
            'action' => $this->webhookUrl("/webhooks/twilio/gather/{$callId}") . "?qk={$questionKey}",
            'method' => 'POST',
            'language' => $language,
            'speechTimeout' => config('ai_calling.call.speech_timeout', 'auto'),
            'timeout' => config('ai_calling.call.silence_timeout', 5),
        ]);

        $gather->say($sayText, [
            'language' => $language,
            'voice' => $voice,
        ]);

        $response->redirect(
            $this->webhookUrl("/webhooks/twilio/fallback/{$callId}") . "?qk={$questionKey}",
            ['method' => 'POST']
        );

        return $response;
    }

    /**
     * Generate TwiML that only speaks text (no input gathering).
     */
    public function generateSayTwiml(string $text): VoiceResponse
    {
        $response = new VoiceResponse();

        $response->say($text, [
            'language' => config('ai_calling.call.language', 'ar-SA'),
            'voice' => config('ai_calling.call.voice', 'Polly.Zeina'),
        ]);

        return $response;
    }

    /**
     * Generate TwiML to say closing text and hang up.
     */
    public function generateClosingTwiml(string $closingText): VoiceResponse
    {
        $response = new VoiceResponse();

        $response->say($closingText, [
            'language' => config('ai_calling.call.language', 'ar-SA'),
            'voice' => config('ai_calling.call.voice', 'Polly.Zeina'),
        ]);

        $response->hangup();

        return $response;
    }

    /**
     * Generate a hangup TwiML.
     */
    public function hangup(): VoiceResponse
    {
        $response = new VoiceResponse();
        $response->hangup();

        return $response;
    }

    /**
     * Fetch call details from Twilio.
     */
    public function getCallStatus(string $callSid): array
    {
        try {
            $call = $this->client()->calls($callSid)->fetch();

            return [
                'sid' => $call->sid,
                'status' => $call->status,
                'duration' => $call->duration,
                'startTime' => $call->startTime?->format('Y-m-d H:i:s'),
                'endTime' => $call->endTime?->format('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            Log::error('Failed to fetch Twilio call status', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);

            return ['sid' => $callSid, 'status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate a Twilio request signature.
     */
    public function validateSignature(string $signature, string $url, array $params): bool
    {
        $token = config('ai_calling.twilio.token');

        $validator = new \Twilio\Security\RequestValidator($token);

        return $validator->validate($signature, $url, $params);
    }
}
