<?php

namespace App\Infrastructure\Ads\Meta;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;

/**
 * Dedicated Meta CRM Conversion Leads writer.
 * This is a convenience wrapper that always sends via the CRM lead path,
 * regardless of the event mapping logic. Useful when you explicitly want
 * to send Lead Ads pipeline events.
 */
final class MetaCrmWriter implements AdsWritePort
{
    public function __construct(
        private readonly MetaClient $client,
        private readonly PlatformEventMapper $mapper,
    ) {}

    public function platform(): Platform
    {
        return Platform::Meta;
    }

    public function sendEvent(OutcomeEvent $event): array
    {
        $mapped = $this->mapper->mapForMeta($event);

        $mapped['is_crm_lead'] = true;
        $mapped['action_source'] = 'system_generated';

        if (! isset($mapped['custom_data']['event_source'])) {
            $mapped['custom_data']['event_source'] = 'crm';
        }
        if (! isset($mapped['custom_data']['lead_event_source'])) {
            $mapped['custom_data']['lead_event_source'] = config('app.name', 'Rakez');
        }

        $userData = $this->buildCrmUserData($event);

        $payload = [
            'event_name' => $event->crmStage ?: $mapped['event_name'],
            'event_time' => $event->occurredAt->unix(),
            'event_id' => $event->eventId,
            'action_source' => 'system_generated',
            'user_data' => $userData,
            'custom_data' => $mapped['custom_data'],
        ];

        $pixelId = config('ads_platforms.meta.pixel_id');
        $body = ['data' => [$payload]];

        $testCode = config('ads_platforms.meta.test_event_code');
        if ($testCode) {
            $body['test_event_code'] = $testCode;
        }

        return $this->client->post("{$pixelId}/events", $body);
    }

    public function sendEventBatch(array $events): array
    {
        $results = [];
        foreach ($events as $event) {
            $results[] = $this->sendEvent($event);
        }

        return $results;
    }

    public function validateEvent(OutcomeEvent $event): array
    {
        return $this->sendEvent($event);
    }

    private function buildCrmUserData(OutcomeEvent $event): array
    {
        $userData = [];

        if ($event->leadId) {
            $userData['lead_id'] = (int) $event->leadId;
        }

        foreach ($event->identifiers as $id) {
            $userData[$id->type] = $id->hashedValue;
        }

        if ($event->metaFbc) {
            $userData['fbc'] = $event->metaFbc;
        }
        if ($event->metaFbp) {
            $userData['fbp'] = $event->metaFbp;
        }

        return $userData;
    }
}
