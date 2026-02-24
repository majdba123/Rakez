<?php

namespace App\Infrastructure\Ads\Persistence;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\OutcomeStorePort;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Persistence\Models\AdsOutcomeEvent;
use Carbon\CarbonImmutable;

final class EloquentOutcomeStore implements OutcomeStorePort
{
    public function enqueue(OutcomeEvent $event): void
    {
        foreach ($event->targetPlatforms as $platform) {
            AdsOutcomeEvent::updateOrCreate(
                [
                    'event_id' => $event->eventId,
                    'platform' => $platform->value,
                ],
                [
                    'outcome_type' => $event->outcomeType->value,
                    'occurred_at' => $event->occurredAt,
                    'status' => 'pending',
                    'value' => $event->value?->amount,
                    'currency' => $event->value?->currency,
                    'crm_stage' => $event->crmStage,
                    'score' => $event->score,
                    'lead_id' => $event->leadId,
                    'hashed_identifiers' => collect($event->identifiers)
                        ->map(fn (HashedIdentifier $h) => ['type' => $h->type, 'value' => $h->hashedValue])
                        ->all(),
                    'click_ids' => array_filter([
                        'meta_fbc' => $event->metaFbc,
                        'meta_fbp' => $event->metaFbp,
                        'snap_click_id' => $event->snapClickId,
                        'snap_cookie1' => $event->snapCookie1,
                        'tiktok_ttclid' => $event->tiktokTtclid,
                        'tiktok_ttp' => $event->tiktokTtp,
                    ]),
                    'payload' => [
                        'client_ip' => $event->clientIp,
                        'client_user_agent' => $event->clientUserAgent,
                        'event_source_url' => $event->eventSourceUrl,
                        'custom_data' => $event->customData,
                    ],
                ],
            );
        }
    }

    public function fetchPending(int $limit = 50): array
    {
        return AdsOutcomeEvent::where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AdsOutcomeEvent $row) => $this->toEntity($row))
            ->all();
    }

    public function markDelivered(string $eventId, string $platform, array $response = []): void
    {
        AdsOutcomeEvent::where('event_id', $eventId)
            ->where('platform', $platform)
            ->update([
                'status' => 'delivered',
                'platform_response' => $response,
                'last_attempted_at' => now(),
            ]);
    }

    public function markFailed(string $eventId, string $platform, string $error): void
    {
        AdsOutcomeEvent::where('event_id', $eventId)
            ->where('platform', $platform)
            ->update([
                'status' => 'pending',
                'last_error' => $error,
                'last_attempted_at' => now(),
                'retry_count' => \DB::raw('retry_count + 1'),
            ]);
    }

    public function moveToDeadLetter(int $maxRetries = 5): int
    {
        return AdsOutcomeEvent::where('status', 'pending')
            ->where('retry_count', '>=', $maxRetries)
            ->update(['status' => 'dead_letter']);
    }

    private function toEntity(AdsOutcomeEvent $row): OutcomeEvent
    {
        $identifiers = collect($row->hashed_identifiers ?? [])
            ->map(fn (array $h) => new HashedIdentifier($h['type'], $h['value'], true))
            ->all();

        $clickIds = $row->click_ids ?? [];
        $payload = $row->payload ?? [];

        return new OutcomeEvent(
            eventId: $row->event_id,
            outcomeType: OutcomeType::from($row->outcome_type),
            occurredAt: CarbonImmutable::parse($row->occurred_at),
            identifiers: $identifiers,
            targetPlatforms: [Platform::from($row->platform)],
            value: $row->value !== null ? new Money((float) $row->value, $row->currency ?? 'USD') : null,
            crmStage: $row->crm_stage,
            score: $row->score,
            leadId: $row->lead_id,
            metaFbc: $clickIds['meta_fbc'] ?? null,
            metaFbp: $clickIds['meta_fbp'] ?? null,
            snapClickId: $clickIds['snap_click_id'] ?? null,
            snapCookie1: $clickIds['snap_cookie1'] ?? null,
            tiktokTtclid: $clickIds['tiktok_ttclid'] ?? null,
            tiktokTtp: $clickIds['tiktok_ttp'] ?? null,
            clientIp: $payload['client_ip'] ?? null,
            clientUserAgent: $payload['client_user_agent'] ?? null,
            eventSourceUrl: $payload['event_source_url'] ?? null,
            customData: $payload['custom_data'] ?? [],
        );
    }
}
