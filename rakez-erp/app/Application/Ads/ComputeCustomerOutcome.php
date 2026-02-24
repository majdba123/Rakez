<?php

namespace App\Application\Ads;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\Ports\OutcomeStorePort;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\Services\EventIdGenerator;
use App\Infrastructure\Ads\Services\HashingService;
use Carbon\CarbonImmutable;

final class ComputeCustomerOutcome
{
    public function __construct(
        private readonly OutcomeStorePort $store,
        private readonly HashingService $hasher,
        private readonly EventIdGenerator $idGenerator,
    ) {}

    /**
     * Compute and enqueue an outcome event from a CRM/Order/Retention signal.
     *
     * @param  array{
     *     customer_id: string,
     *     email?: string,
     *     phone?: string,
     *     outcome_type: string,
     *     occurred_at: string,
     *     value?: float,
     *     currency?: string,
     *     crm_stage?: string,
     *     score?: int,
     *     lead_id?: string,
     *     order_id?: string,
     *     meta_fbc?: string,
     *     meta_fbp?: string,
     *     snap_click_id?: string,
     *     snap_cookie1?: string,
     *     tiktok_ttclid?: string,
     *     tiktok_ttp?: string,
     *     client_ip?: string,
     *     client_user_agent?: string,
     *     event_source_url?: string,
     *     platforms?: string[],
     * }  $data
     */
    public function execute(array $data): OutcomeEvent
    {
        $outcomeType = OutcomeType::from($data['outcome_type']);
        $occurredAt = CarbonImmutable::parse($data['occurred_at']);

        $platforms = array_map(
            fn (string $p) => Platform::from($p),
            $data['platforms'] ?? ['meta', 'snap', 'tiktok'],
        );

        $identifiers = [];
        if (! empty($data['email'])) {
            $identifiers[] = new HashedIdentifier('em', $this->hasher->hashEmail($data['email']));
        }
        if (! empty($data['phone'])) {
            $identifiers[] = new HashedIdentifier('ph', $this->hasher->hashPhone($data['phone']));
        }
        if (! empty($data['customer_id'])) {
            $identifiers[] = new HashedIdentifier('external_id', $this->hasher->hashExternalId($data['customer_id']));
        }

        $eventId = $this->idGenerator->generate(
            $platforms[0],
            $data['customer_id'],
            $outcomeType,
            $occurredAt,
            $data['order_id'] ?? null,
        );

        $event = new OutcomeEvent(
            eventId: $eventId,
            outcomeType: $outcomeType,
            occurredAt: $occurredAt,
            identifiers: $identifiers,
            targetPlatforms: $platforms,
            value: isset($data['value']) ? new Money((float) $data['value'], $data['currency'] ?? 'USD') : null,
            crmStage: $data['crm_stage'] ?? null,
            score: $data['score'] ?? null,
            leadId: $data['lead_id'] ?? null,
            metaFbc: $data['meta_fbc'] ?? null,
            metaFbp: $data['meta_fbp'] ?? null,
            snapClickId: $data['snap_click_id'] ?? null,
            snapCookie1: $data['snap_cookie1'] ?? null,
            tiktokTtclid: $data['tiktok_ttclid'] ?? null,
            tiktokTtp: $data['tiktok_ttp'] ?? null,
            clientIp: $data['client_ip'] ?? null,
            clientUserAgent: $data['client_user_agent'] ?? null,
            eventSourceUrl: $data['event_source_url'] ?? null,
        );

        $this->store->enqueue($event);

        return $event;
    }
}
