<?php

namespace Tests\Unit\Ads\EventMapping;

use App\Domain\Ads\Entities\OutcomeEvent;
use App\Domain\Ads\ValueObjects\HashedIdentifier;
use App\Domain\Ads\ValueObjects\Money;
use App\Domain\Ads\ValueObjects\OutcomeType;
use App\Domain\Ads\ValueObjects\Platform;
use App\Infrastructure\Ads\EventMapping\PlatformEventMapper;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlatformEventMapperTest extends TestCase
{
    private PlatformEventMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new PlatformEventMapper();
    }

    private function makeEvent(
        OutcomeType $type,
        ?Money $value = null,
        ?string $leadId = null,
        ?string $crmStage = null,
        ?int $score = null,
    ): OutcomeEvent {
        return new OutcomeEvent(
            eventId: 'test_evt_1',
            outcomeType: $type,
            occurredAt: CarbonImmutable::parse('2026-01-15'),
            identifiers: [new HashedIdentifier('em', hash('sha256', 'test@test.com'))],
            targetPlatforms: [Platform::Meta],
            value: $value,
            crmStage: $crmStage,
            score: $score,
            leadId: $leadId,
        );
    }

    // === Meta Standard CAPI mapping ===

    public function test_meta_purchase_maps_to_purchase_event(): void
    {
        $event = $this->makeEvent(OutcomeType::Purchase, new Money(100, 'USD'));
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Purchase', $mapped['event_name']);
        $this->assertSame('website', $mapped['action_source']);
        $this->assertFalse($mapped['is_crm_lead']);
        $this->assertSame(100.0, $mapped['custom_data']['value']);
    }

    public function test_meta_deal_won_maps_to_purchase(): void
    {
        $event = $this->makeEvent(OutcomeType::DealWon, new Money(5000, 'SAR'));
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Purchase', $mapped['event_name']);
        $this->assertFalse($mapped['is_crm_lead']);
    }

    public function test_meta_refund_maps_to_purchase_with_negative_value(): void
    {
        $event = $this->makeEvent(OutcomeType::Refund, new Money(50, 'USD'));
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Purchase', $mapped['event_name']);
        $this->assertSame(-50.0, $mapped['custom_data']['value']);
    }

    public function test_meta_retention_maps_to_subscribe(): void
    {
        $event = $this->makeEvent(OutcomeType::RetentionD7);
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Subscribe', $mapped['event_name']);
    }

    public function test_meta_ltv_update_maps_to_purchase(): void
    {
        $event = $this->makeEvent(OutcomeType::LtvUpdate, new Money(1500, 'USD'));
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Purchase', $mapped['event_name']);
    }

    public function test_meta_lead_qualified_without_lead_id_maps_to_lead(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, score: 85);
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Lead', $mapped['event_name']);
        $this->assertFalse($mapped['is_crm_lead']);
    }

    // === Meta CRM Conversion Leads path ===

    public function test_meta_crm_lead_path_activates_with_lead_id(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, leadId: '1234567890123456', crmStage: 'MQL');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertTrue($mapped['is_crm_lead']);
        $this->assertSame('system_generated', $mapped['action_source']);
    }

    public function test_meta_crm_lead_event_name_uses_crm_stage_when_set(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, leadId: '123', crmStage: 'Marketing Qualified Lead');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Marketing Qualified Lead', $mapped['event_name']);
    }

    public function test_meta_crm_lead_event_name_defaults_when_no_stage(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, leadId: '123');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('Marketing Qualified Lead', $mapped['event_name']);
    }

    public function test_meta_crm_deal_won_with_lead_id(): void
    {
        $event = $this->makeEvent(OutcomeType::DealWon, leadId: '123', crmStage: 'Converted');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertTrue($mapped['is_crm_lead']);
        $this->assertSame('Converted', $mapped['event_name']);
    }

    public function test_meta_crm_deal_lost_with_lead_id(): void
    {
        $event = $this->makeEvent(OutcomeType::DealLost, leadId: '123');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertTrue($mapped['is_crm_lead']);
        $this->assertSame('Lost', $mapped['event_name']);
    }

    public function test_meta_crm_custom_data_includes_event_source_crm(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, leadId: '123');
        $mapped = $this->mapper->mapForMeta($event);

        $this->assertSame('crm', $mapped['custom_data']['event_source']);
        $this->assertArrayHasKey('lead_event_source', $mapped['custom_data']);
    }

    // === Snap CAPI mapping ===

    public function test_snap_purchase_maps_to_purchase(): void
    {
        $event = $this->makeEvent(OutcomeType::Purchase, new Money(100, 'USD'));
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('PURCHASE', $mapped['event_name']);
        $this->assertSame('WEB', $mapped['action_source']);
        $this->assertSame(100.0, $mapped['custom_data']['price']);
        $this->assertSame('USD', $mapped['custom_data']['currency']);
    }

    public function test_snap_lead_qualified_maps_to_sign_up(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, score: 80);
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('SIGN_UP', $mapped['event_name']);
    }

    public function test_snap_refund_maps_to_custom_event_3(): void
    {
        $event = $this->makeEvent(OutcomeType::Refund, new Money(50, 'USD'));
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('CUSTOM_EVENT_3', $mapped['event_name']);
        $this->assertSame('OFFLINE', $mapped['action_source']);
    }

    public function test_snap_deal_lost_maps_to_custom_event_2(): void
    {
        $event = $this->makeEvent(OutcomeType::DealLost);
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('CUSTOM_EVENT_2', $mapped['event_name']);
    }

    public function test_snap_retention_maps_to_subscribe(): void
    {
        $event = $this->makeEvent(OutcomeType::RetentionD30);
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('SUBSCRIBE', $mapped['event_name']);
    }

    public function test_snap_ltv_update_includes_predicted_ltv(): void
    {
        $event = $this->makeEvent(OutcomeType::LtvUpdate, new Money(2000, 'USD'));
        $mapped = $this->mapper->mapForSnap($event);

        $this->assertSame('PURCHASE', $mapped['event_name']);
        $this->assertSame(2000.0, $mapped['custom_data']['predicted_ltv']);
    }

    // === TikTok Events API mapping ===

    public function test_tiktok_purchase_maps_to_complete_payment(): void
    {
        $event = $this->makeEvent(OutcomeType::Purchase, new Money(100, 'USD'));
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertSame('CompletePayment', $mapped['event']);
        $this->assertArrayHasKey('properties', $mapped);
        $this->assertSame(100.0, $mapped['properties']['value']);
    }

    public function test_tiktok_uses_event_field_not_event_name(): void
    {
        $event = $this->makeEvent(OutcomeType::Purchase);
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertArrayHasKey('event', $mapped);
        $this->assertArrayNotHasKey('event_name', $mapped);
    }

    public function test_tiktok_lead_qualified_maps_to_submit_form(): void
    {
        $event = $this->makeEvent(OutcomeType::LeadQualified, score: 90);
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertSame('SubmitForm', $mapped['event']);
        $this->assertSame('lead_qualified', $mapped['properties']['description']);
    }

    public function test_tiktok_retention_maps_to_subscribe(): void
    {
        $event = $this->makeEvent(OutcomeType::RetentionD7);
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertSame('Subscribe', $mapped['event']);
    }

    public function test_tiktok_refund_includes_negative_value(): void
    {
        $event = $this->makeEvent(OutcomeType::Refund, new Money(75, 'USD'));
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertSame(-75.0, $mapped['properties']['value']);
        $this->assertSame('refund', $mapped['properties']['description']);
    }

    public function test_tiktok_deal_won_maps_to_complete_payment(): void
    {
        $event = $this->makeEvent(OutcomeType::DealWon, new Money(5000, 'SAR'));
        $mapped = $this->mapper->mapForTikTok($event);

        $this->assertSame('CompletePayment', $mapped['event']);
    }
}
