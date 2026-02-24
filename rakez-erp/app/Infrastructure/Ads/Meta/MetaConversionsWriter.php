<?php

namespace App\Infrastructure\Ads\Meta;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;

/**
 * Meta Conversions API - Standard Web/Offline Events path.
 * Endpoint: POST /{PIXEL_ID}/events
 */
final class MetaConversionsWriter implements AdsWritePort
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

        if ($mapped['is_crm_lead']) {
            return $this->sendCrmLeadEvent($event, $mapped);
        }

        return $this->sendStandardEvent($event, $mapped);
    }

    public function sendEventBatch(array $events): array
    {
        $results = [];
        $standardBatch = [];
        $crmBatch = [];

        foreach ($events as $event) {
            $mapped = $this->mapper->mapForMeta($event);
            if ($mapped['is_crm_lead']) {
                $crmBatch[] = ['event' => $event, 'mapped' => $mapped];
            } else {
                $standardBatch[] = ['event' => $event, 'mapped' => $mapped];
            }
        }

        if (! empty($standardBatch)) {
            $payload = array_map(
                fn ($item) => $this->buildStandardPayload($item['event'], $item['mapped']),
                $standardBatch,
            );
            $results['standard'] = $this->postEvents($payload);
        }

        if (! empty($crmBatch)) {
            $payload = array_map(
                fn ($item) => $this->buildCrmLeadPayload($item['event'], $item['mapped']),
                $crmBatch,
            );
            $results['crm'] = $this->postEvents($payload);
        }

        return $results;
    }

    public function validateEvent(OutcomeEvent $event): array
    {
        $mapped = $this->mapper->mapForMeta($event);
        $testCode = config('ads_platforms.meta.test_event_code');

        if ($mapped['is_crm_lead']) {
            $payload = $this->buildCrmLeadPayload($event, $mapped);
        } else {
            $payload = $this->buildStandardPayload($event, $mapped);
        }

        $body = ['data' => [$payload]];
        if ($testCode) {
            $body['test_event_code'] = $testCode;
        }

        $pixelId = config('ads_platforms.meta.pixel_id');

        return $this->client->post("{$pixelId}/events", $body);
    }

    private function sendStandardEvent(OutcomeEvent $event, array $mapped): array
    {
        $payload = $this->buildStandardPayload($event, $mapped);

        return $this->postEvents([$payload]);
    }

    private function sendCrmLeadEvent(OutcomeEvent $event, array $mapped): array
    {
        $payload = $this->buildCrmLeadPayload($event, $mapped);

        return $this->postEvents([$payload]);
    }

    private function buildStandardPayload(OutcomeEvent $event, array $mapped): array
    {
        $userData = $this->buildUserData($event);

        $payload = [
            'event_name' => $mapped['event_name'],
            'event_time' => $event->occurredAt->unix(),
            'event_id' => $event->eventId,
            'action_source' => $mapped['action_source'],
            'user_data' => $userData,
        ];

        if ($event->eventSourceUrl) {
            $payload['event_source_url'] = $event->eventSourceUrl;
        }

        if (! empty($mapped['custom_data'])) {
            $payload['custom_data'] = $mapped['custom_data'];
        }

        return $payload;
    }

    /**
     * CRM Conversion Leads payload (per Meta's CRM Integration Payload Spec):
     * - action_source = system_generated
     * - event_name = free-form CRM stage name
     * - custom_data must contain event_source=crm and lead_event_source
     * - lead_id is the preferred matching identifier
     */
    private function buildCrmLeadPayload(OutcomeEvent $event, array $mapped): array
    {
        $userData = $this->buildUserData($event);

        if ($event->leadId) {
            $userData['lead_id'] = (int) $event->leadId;
        }

        return [
            'event_name' => $mapped['event_name'],
            'event_time' => $event->occurredAt->unix(),
            'event_id' => $event->eventId,
            'action_source' => 'system_generated',
            'user_data' => $userData,
            'custom_data' => $mapped['custom_data'],
        ];
    }

    /**
     * Build user_data with hashed identifiers + non-hashed click IDs.
     * Per Meta docs: em/ph/fn/ln/ct/st/zp/db/ge/country/external_id = hashed.
     * fbc/fbp/client_ip_address/client_user_agent/lead_id = NOT hashed.
     */
    private function buildUserData(OutcomeEvent $event): array
    {
        $userData = [];

        foreach ($event->identifiers as $id) {
            $userData[$id->type] = $id->hashedValue;
        }

        if ($event->metaFbc) {
            $userData['fbc'] = $event->metaFbc;
        }
        if ($event->metaFbp) {
            $userData['fbp'] = $event->metaFbp;
        }
        if ($event->clientIp) {
            $userData['client_ip_address'] = $event->clientIp;
        }
        if ($event->clientUserAgent) {
            $userData['client_user_agent'] = $event->clientUserAgent;
        }

        return $userData;
    }

    private function postEvents(array $eventsData): array
    {
        $pixelId = config('ads_platforms.meta.pixel_id');
        $body = ['data' => $eventsData];

        $testCode = config('ads_platforms.meta.test_event_code');
        if ($testCode) {
            $body['test_event_code'] = $testCode;
        }

        return $this->client->post("{$pixelId}/events", $body);
    }
}
