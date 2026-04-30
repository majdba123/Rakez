<?php

namespace App\Services\Notifications;

use Twilio\Rest\Client as TwilioClient;

class TwilioSmsService
{
    private ?TwilioClient $client = null;

    public function send(string $to, string $message): SmsSendResult
    {
        $messageInstance = $this->client()->messages->create($to, [
            'from' => $this->fromNumber(),
            'body' => $message,
        ]);

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
        return (string) (config('sales.unit_search_alerts.from_number') ?: config('ai_calling.twilio.from_number', ''));
    }
}
