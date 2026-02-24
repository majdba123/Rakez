<?php

namespace App\Infrastructure\Ads\EventMapping;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;

/**
 * Maps canonical OutcomeEvent to platform-specific event names and payloads.
 * Follows the mapping table from the plan, verified against official docs.
 */
final class PlatformEventMapper
{
    public function __construct(
        private readonly string $appName = 'Rakez',
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('app.name', 'Rakez'));
    }

    /**
     * @return array{event_name: string, action_source: string, custom_data: array, is_crm_lead: bool}
     */
    public function mapForMeta(OutcomeEvent $event): array
    {
        $isCrmLead = $this->shouldUseCrmLeadPath($event);

        if ($isCrmLead) {
            return [
                'event_name' => $this->metaCrmEventName($event),
                'action_source' => 'system_generated',
                'custom_data' => array_merge([
                    'event_source' => 'crm',
                    'lead_event_source' => $this->appName,
                ], $event->customData),
                'is_crm_lead' => true,
            ];
        }

        return match ($event->outcomeType) {
            OutcomeType::Purchase, OutcomeType::DealWon => [
                'event_name' => 'Purchase',
                'action_source' => 'website',
                'custom_data' => $this->metaPurchaseData($event),
                'is_crm_lead' => false,
            ],
            OutcomeType::Refund => [
                'event_name' => 'Purchase',
                'action_source' => 'website',
                'custom_data' => $this->metaRefundData($event),
                'is_crm_lead' => false,
            ],
            OutcomeType::RetentionD7, OutcomeType::RetentionD30 => [
                'event_name' => 'Subscribe',
                'action_source' => 'website',
                'custom_data' => $event->customData,
                'is_crm_lead' => false,
            ],
            OutcomeType::LtvUpdate => [
                'event_name' => 'Purchase',
                'action_source' => 'website',
                'custom_data' => $this->metaPurchaseData($event),
                'is_crm_lead' => false,
            ],
            OutcomeType::LeadQualified => [
                'event_name' => 'Lead',
                'action_source' => 'website',
                'custom_data' => array_merge(['lead_score' => $event->score], $event->customData),
                'is_crm_lead' => false,
            ],
            OutcomeType::LeadDisqualified, OutcomeType::DealLost => [
                'event_name' => 'Lead',
                'action_source' => 'website',
                'custom_data' => array_merge(['lead_disqualified' => true], $event->customData),
                'is_crm_lead' => false,
            ],
        };
    }

    /**
     * @return array{event_name: string, action_source: string, custom_data: array}
     */
    public function mapForSnap(OutcomeEvent $event): array
    {
        return match ($event->outcomeType) {
            OutcomeType::Purchase, OutcomeType::DealWon => [
                'event_name' => 'PURCHASE',
                'action_source' => 'WEB',
                'custom_data' => $this->snapPurchaseData($event),
            ],
            OutcomeType::Refund => [
                'event_name' => 'CUSTOM_EVENT_3',
                'action_source' => 'OFFLINE',
                'custom_data' => $this->snapRefundData($event),
            ],
            OutcomeType::LeadQualified => [
                'event_name' => 'SIGN_UP',
                'action_source' => 'WEB',
                'custom_data' => array_merge(['lead_score' => $event->score], $event->customData),
            ],
            OutcomeType::LeadDisqualified => [
                'event_name' => 'CUSTOM_EVENT_2',
                'action_source' => 'OFFLINE',
                'custom_data' => $event->customData,
            ],
            OutcomeType::DealLost => [
                'event_name' => 'CUSTOM_EVENT_2',
                'action_source' => 'OFFLINE',
                'custom_data' => $event->customData,
            ],
            OutcomeType::RetentionD7, OutcomeType::RetentionD30 => [
                'event_name' => 'SUBSCRIBE',
                'action_source' => 'WEB',
                'custom_data' => $event->customData,
            ],
            OutcomeType::LtvUpdate => [
                'event_name' => 'PURCHASE',
                'action_source' => 'WEB',
                'custom_data' => array_merge(
                    ['predicted_ltv' => $event->value?->amount],
                    $this->snapPurchaseData($event),
                ),
            ],
        };
    }

    /**
     * @return array{event: string, properties: array}
     */
    public function mapForTikTok(OutcomeEvent $event): array
    {
        return match ($event->outcomeType) {
            OutcomeType::Purchase, OutcomeType::DealWon => [
                'event' => 'CompletePayment',
                'properties' => $this->tikTokPurchaseProperties($event),
            ],
            OutcomeType::Refund => [
                'event' => 'CompletePayment',
                'properties' => $this->tikTokRefundProperties($event),
            ],
            OutcomeType::LeadQualified => [
                'event' => 'SubmitForm',
                'properties' => array_merge(
                    ['description' => 'lead_qualified', 'value' => $event->score],
                    $event->customData,
                ),
            ],
            OutcomeType::LeadDisqualified => [
                'event' => 'SubmitForm',
                'properties' => array_merge(
                    ['description' => 'lead_disqualified'],
                    $event->customData,
                ),
            ],
            OutcomeType::DealLost => [
                'event' => 'SubmitForm',
                'properties' => array_merge(
                    ['description' => 'deal_lost'],
                    $event->customData,
                ),
            ],
            OutcomeType::RetentionD7, OutcomeType::RetentionD30 => [
                'event' => 'Subscribe',
                'properties' => $event->customData,
            ],
            OutcomeType::LtvUpdate => [
                'event' => 'CompletePayment',
                'properties' => $this->tikTokPurchaseProperties($event),
            ],
        };
    }

    private function shouldUseCrmLeadPath(OutcomeEvent $event): bool
    {
        $crmLeadTypes = [
            OutcomeType::LeadQualified,
            OutcomeType::LeadDisqualified,
            OutcomeType::DealWon,
            OutcomeType::DealLost,
        ];

        return in_array($event->outcomeType, $crmLeadTypes) && $event->leadId !== null;
    }

    private function metaCrmEventName(OutcomeEvent $event): string
    {
        if ($event->crmStage) {
            return $event->crmStage;
        }

        return match ($event->outcomeType) {
            OutcomeType::LeadQualified => 'Marketing Qualified Lead',
            OutcomeType::LeadDisqualified => 'Disqualified',
            OutcomeType::DealWon => 'Converted',
            OutcomeType::DealLost => 'Lost',
            default => $event->outcomeType->value,
        };
    }

    private function metaPurchaseData(OutcomeEvent $event): array
    {
        $data = $event->customData;
        if ($event->value) {
            $data['value'] = $event->value->amount;
            $data['currency'] = $event->value->currency;
        }

        return $data;
    }

    private function metaRefundData(OutcomeEvent $event): array
    {
        $data = $event->customData;
        if ($event->value) {
            $data['value'] = -abs($event->value->amount);
            $data['currency'] = $event->value->currency;
        }

        return $data;
    }

    private function snapPurchaseData(OutcomeEvent $event): array
    {
        $data = [];
        if ($event->value) {
            $data['price'] = $event->value->amount;
            $data['currency'] = $event->value->currency;
        }

        return array_merge($data, $event->customData);
    }

    private function snapRefundData(OutcomeEvent $event): array
    {
        $data = $event->customData;
        if ($event->value) {
            $data['price'] = -abs($event->value->amount);
            $data['currency'] = $event->value->currency;
        }

        return $data;
    }

    private function tikTokPurchaseProperties(OutcomeEvent $event): array
    {
        $props = [];
        if ($event->value) {
            $props['value'] = $event->value->amount;
            $props['currency'] = $event->value->currency;
        }

        return array_merge($props, $event->customData);
    }

    private function tikTokRefundProperties(OutcomeEvent $event): array
    {
        $props = $event->customData;
        if ($event->value) {
            $props['value'] = -abs($event->value->amount);
            $props['currency'] = $event->value->currency;
        }
        $props['description'] = 'refund';

        return $props;
    }
}
