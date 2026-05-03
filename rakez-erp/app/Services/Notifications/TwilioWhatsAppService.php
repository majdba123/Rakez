<?php

namespace App\Services\Notifications;

use Twilio\Rest\Client as TwilioClient;

class TwilioWhatsAppService
{
    private ?TwilioClient $client = null;

    public function send(string $to, string $message, ?string $mediaUrl = null): SmsSendResult
    {
        $payload = [
            'from' => $this->whatsappNumber($this->fromNumber()),
            'body' => $message,
        ];

        if ($mediaUrl) {
            $payload['mediaUrl'] = [$mediaUrl];
        }

        $messageInstance = $this->client()->messages->create(
            $this->whatsappNumber($to),
            $payload
        );

        return new SmsSendResult((string) $messageInstance->sid);
    }

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

    private function fromNumber(): string
    {
        return (string) (
            config('services.twilio.whatsapp_from')
            ?: config('sales.unit_search_alerts.from_number')
            ?: config('ai_calling.twilio.from_number', '')
        );
    }

    private function whatsappNumber(string $number): string
    {
        $number = trim($number);

        return str_starts_with($number, 'whatsapp:')
            ? $number
            : 'whatsapp:' . $number;
    }
}
