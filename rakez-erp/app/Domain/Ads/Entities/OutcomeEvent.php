<?php

namespace App\Domain\Ads\Entities;

use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use Carbon\CarbonImmutable;

final class OutcomeEvent
{
    /**
     * @param  HashedIdentifier[]  $identifiers
     * @param  Platform[]  $targetPlatforms
     */
    public function __construct(
        public readonly string $eventId,
        public readonly OutcomeType $outcomeType,
        public readonly CarbonImmutable $occurredAt,
        public readonly array $identifiers,
        public readonly array $targetPlatforms,
        public readonly ?Money $value = null,
        public readonly ?string $crmStage = null,
        public readonly ?int $score = null,
        public readonly ?string $leadId = null,
        public readonly ?string $metaFbc = null,
        public readonly ?string $metaFbp = null,
        public readonly ?string $snapClickId = null,
        public readonly ?string $snapCookie1 = null,
        public readonly ?string $tiktokTtclid = null,
        public readonly ?string $tiktokTtp = null,
        public readonly ?string $clientIp = null,
        public readonly ?string $clientUserAgent = null,
        public readonly ?string $eventSourceUrl = null,
        public readonly array $customData = [],
    ) {}
}
