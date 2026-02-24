<?php

namespace App\Infrastructure\Ads\TikTok;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;

/**
 * TikTok Events API writer (consolidated endpoint).
 * Endpoint: POST /open_api/v1.3/event/track/
 *
 * Key requirements from docs:
 * - Field name is `event` (NOT `event_name`)
 * - context.ad.callback = ttclid (NOT hashed)
 * - context.user.ttp = _ttp cookie (NOT hashed)
 * - context.user.email/phone_number/external_id = SHA-256 hashed
 * - context.ip and context.user_agent = NOT hashed
 * - event_id for dedup (48h window, 5min min delay)
 * - EMQ score target: 8.0+ for optimal attribution
 */
final class TikTokEventsWriter implements AdsWritePort
{
    public function __construct(
        private readonly TikTokClient $client,
        private readonly PlatformEventMapper $mapper,
    ) {}

    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    public function sendEvent(OutcomeEvent $event): array
    {
        $payload = $this->buildPayload($event);

        return $this->client->post('event/track/', $payload);
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

    private function buildPayload(OutcomeEvent $event): array
    {
        $mapped = $this->mapper->mapForTikTok($event);
        $pixelCode = config('ads_platforms.tiktok.pixel_code');

        $eventData = [
            'pixel_code' => $pixelCode,
            'event' => $mapped['event'],
            'event_id' => $event->eventId,
            'timestamp' => $event->occurredAt->toIso8601String(),
            'context' => $this->buildContext($event),
        ];

        if (! empty($mapped['properties'])) {
            $eventData['properties'] = $mapped['properties'];
        }

        return $eventData;
    }

    /**
     * Build the context object per TikTok Events API spec:
     * - context.user.email = SHA-256 hashed
     * - context.user.phone_number = SHA-256 hashed
     * - context.user.external_id = SHA-256 hashed
     * - context.user.ttp = _ttp cookie value (NOT hashed)
     * - context.ad.callback = ttclid (NOT hashed)
     * - context.ip = non-hashed
     * - context.user_agent = non-hashed
     * - context.page = page URL info
     */
    private function buildContext(OutcomeEvent $event): array
    {
        $context = [];

        $user = [];
        foreach ($event->identifiers as $id) {
            match ($id->type) {
                'em' => $user['email'] = $id->hashedValue,
                'ph' => $user['phone_number'] = $id->hashedValue,
                'external_id' => $user['external_id'] = $id->hashedValue,
                default => null,
            };
        }

        if ($event->tiktokTtp) {
            $user['ttp'] = $event->tiktokTtp;
        }

        if (! empty($user)) {
            $context['user'] = $user;
        }

        if ($event->tiktokTtclid) {
            $context['ad'] = ['callback' => $event->tiktokTtclid];
        }

        if ($event->clientIp) {
            $context['ip'] = $event->clientIp;
        }
        if ($event->clientUserAgent) {
            $context['user_agent'] = $event->clientUserAgent;
        }

        if ($event->eventSourceUrl) {
            $context['page'] = ['url' => $event->eventSourceUrl];
        }

        return $context;
    }
}
