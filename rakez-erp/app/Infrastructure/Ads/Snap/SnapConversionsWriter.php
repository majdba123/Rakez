<?php

namespace App\Infrastructure\Ads\Snap;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\AdsWritePort;
use App\Domain\Ads\Ports\TokenStorePort;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;
use Illuminate\Support\Facades\Http;

/**
 * Snap Conversions API v3 writer.
 * Endpoint: POST https://tr.snapchat.com/v3/{PIXEL_ID}/events
 *
 * Key requirements from docs:
 * - Hashing: em/ph/fn/ln/ge/ct/st/zp/country = SHA-256 after normalization
 * - NOT hashed: client_ip_address, client_user_agent, sc_click_id, sc_cookie1, madid, lead_id
 * - Dedup: event_id maps to client_dedup_id (non-purchase) or transaction_id (purchase)
 * - Dedup window: 48 hours
 * - event_time: max 7 days in the past
 * - PURCHASE requires value + currency
 */
final class SnapConversionsWriter implements AdsWritePort
{
    private string $capiBaseUrl;

    public function __construct(
        private readonly TokenStorePort $tokenStore,
        private readonly PlatformEventMapper $mapper,
    ) {
        $this->capiBaseUrl = config('ads_platforms.snap.capi_base_url', 'https://tr.snapchat.com/v3');
    }

    public function platform(): Platform
    {
        return Platform::Snap;
    }

    public function sendEvent(OutcomeEvent $event): array
    {
        $payload = $this->buildPayload($event);
        $pixelId = config('ads_platforms.snap.pixel_id');
        $token = $this->getToken($event);

        return Http::baseUrl($this->capiBaseUrl)
            ->timeout(15)
            ->retry(3, 1000)
            ->post("{$pixelId}/events?access_token={$token}", $payload)
            ->throw()
            ->json();
    }

    public function sendEventBatch(array $events): array
    {
        $pixelId = config('ads_platforms.snap.pixel_id');
        $token = $this->getToken($events[0] ?? null);

        $eventsPayload = array_map(fn ($e) => $this->buildSingleEvent($e), $events);

        return Http::baseUrl($this->capiBaseUrl)
            ->timeout(30)
            ->retry(3, 1000)
            ->post("{$pixelId}/events?access_token={$token}", $eventsPayload)
            ->throw()
            ->json();
    }

    public function validateEvent(OutcomeEvent $event): array
    {
        $payload = $this->buildPayload($event);
        $pixelId = config('ads_platforms.snap.pixel_id');
        $token = $this->getToken($event);

        return Http::baseUrl($this->capiBaseUrl)
            ->timeout(15)
            ->post("{$pixelId}/events/validate?access_token={$token}", $payload)
            ->throw()
            ->json();
    }

    private function buildPayload(OutcomeEvent $event): array
    {
        return [$this->buildSingleEvent($event)];
    }

    private function buildSingleEvent(OutcomeEvent $event): array
    {
        $mapped = $this->mapper->mapForSnap($event);

        $eventData = [
            'event_name' => $mapped['event_name'],
            'event_time' => $event->occurredAt->unix() * 1000,
            'action_source' => $mapped['action_source'],
            'user_data' => $this->buildUserData($event),
        ];

        if ($event->eventSourceUrl && $mapped['action_source'] === 'WEB') {
            $eventData['event_source_url'] = $event->eventSourceUrl;
        }

        $isPurchase = in_array($mapped['event_name'], ['PURCHASE']);
        if ($isPurchase) {
            $eventData['transaction_id'] = $event->eventId;
            if ($event->value) {
                $eventData['price'] = abs($event->value->amount);
                $eventData['currency'] = $event->value->currency;
            }
        } else {
            $eventData['client_dedup_id'] = $event->eventId;
        }

        if (! empty($mapped['custom_data'])) {
            foreach ($mapped['custom_data'] as $k => $v) {
                if (! isset($eventData[$k])) {
                    $eventData[$k] = $v;
                }
            }
        }

        return $eventData;
    }

    /**
     * Build user_data per Snap CAPI v3 requirements:
     * - Hashed fields: em, ph, fn, ln, ge, ct, st, zp, country, external_id
     * - Non-hashed: client_ip_address, client_user_agent, sc_click_id, sc_cookie1
     */
    private function buildUserData(OutcomeEvent $event): array
    {
        $userData = [];

        foreach ($event->identifiers as $id) {
            $userData[$id->type] = $id->hashedValue;
        }

        if ($event->snapClickId) {
            $userData['sc_click_id'] = $event->snapClickId;
        }
        if ($event->snapCookie1) {
            $userData['sc_cookie1'] = $event->snapCookie1;
        }
        if ($event->clientIp) {
            $userData['client_ip_address'] = $event->clientIp;
        }
        if ($event->clientUserAgent) {
            $userData['client_user_agent'] = $event->clientUserAgent;
        }

        return $userData;
    }

    private function getToken(?OutcomeEvent $event): string
    {
        $accountId = config('ads_platforms.snap.ad_account_id', '');

        return $this->tokenStore->getAccessToken(Platform::Snap, $accountId)
            ?? throw new \RuntimeException('No Snap access token available for CAPI');
    }
}
